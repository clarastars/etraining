<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RecordedCourseLessonVideoChunkUploadService
{
    private const PROGRESS_PREFIX = 'rc_lesson_vid_upload:';

    private const READY_PREFIX = 'rc_lesson_vid_ready:';

    private const MAX_CHUNK_BYTES = 12 * 1024 * 1024;

    /**
     * @param  array{file_name: string, mime_type: string|null, total_size: int}  $meta
     */
    public function start(int $userId, array $meta): string
    {
        $maxFileBytes = (int) config('media-library.max_file_size', 524_288_000);
        $totalSize = (int) $meta['total_size'];
        if ($totalSize < 1 || $totalSize > $maxFileBytes) {
            throw new HttpException(422, __('validation.max.file', [
                'attribute' => 'video',
                'max' => (int) ($maxFileBytes / 1024),
            ]));
        }

        $uploadId = (string) Str::uuid();
        $dir = storage_path('app/tmp/rc-lesson-uploads');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new HttpException(500, 'Could not create upload directory.');
        }

        $path = $dir.'/'.$uploadId.'.part';
        if (file_put_contents($path, '') === false) {
            throw new HttpException(500, 'Could not initialize upload file.');
        }

        Cache::put($this->progressKey($uploadId), [
            'user_id' => $userId,
            'original_name' => $meta['file_name'],
            'claimed_mime' => $meta['mime_type'] ?? '',
            'total_size' => $totalSize,
            'next_chunk_index' => 0,
            'bytes_written' => 0,
            'path' => $path,
        ], now()->addHours(24));

        return $uploadId;
    }

    public function appendChunk(int $userId, string $uploadId, int $chunkIndex, string $binary): void
    {
        $data = Cache::get($this->progressKey($uploadId));
        if (! is_array($data) || ($data['user_id'] ?? null) !== $userId) {
            throw new HttpException(404, 'Upload not found.');
        }

        if ($chunkIndex !== (int) $data['next_chunk_index']) {
            throw new HttpException(409, 'Chunks must be uploaded in order.');
        }

        $len = strlen($binary);
        if ($len < 1) {
            throw new HttpException(422, 'Empty chunk.');
        }

        if ($len > self::MAX_CHUNK_BYTES) {
            throw new HttpException(422, 'Chunk too large.');
        }

        if ($data['bytes_written'] + $len > (int) $data['total_size']) {
            throw new HttpException(422, 'Chunk exceeds declared file size.');
        }

        $path = (string) $data['path'];
        if (file_put_contents($path, $binary, FILE_APPEND | LOCK_EX) === false) {
            throw new HttpException(500, 'Could not write chunk.');
        }

        $data['bytes_written'] = (int) $data['bytes_written'] + $len;
        $data['next_chunk_index'] = (int) $data['next_chunk_index'] + 1;

        Cache::put($this->progressKey($uploadId), $data, now()->addHours(24));
    }

    public function complete(int $userId, string $uploadId): string
    {
        $key = $this->progressKey($uploadId);
        $data = Cache::get($key);
        if (! is_array($data) || ($data['user_id'] ?? null) !== $userId) {
            throw new HttpException(404, 'Upload not found.');
        }

        if ((int) $data['bytes_written'] !== (int) $data['total_size']) {
            throw new HttpException(422, 'Upload incomplete.');
        }

        $path = (string) $data['path'];
        if (! is_file($path)) {
            throw new HttpException(404, 'Upload file missing.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($path) ?: '';
        $allowed = ['video/mp4', 'video/webm', 'video/quicktime'];
        if (! in_array($detected, $allowed, true)) {
            @unlink($path);
            Cache::forget($key);

            throw new HttpException(422, 'Invalid or unsupported video type.');
        }

        $token = (string) Str::uuid();
        $dir = dirname($path);
        $readyPath = $dir.'/'.$token.'.ready';

        if (! rename($path, $readyPath)) {
            throw new HttpException(500, 'Could not finalize upload.');
        }

        Cache::forget($key);

        Cache::put($this->readyKey($token), [
            'user_id' => $userId,
            'path' => $readyPath,
            'mime' => $detected,
            'original_name' => (string) $data['original_name'],
        ], now()->addHours(2));

        return $token;
    }

    /**
     * @return array{path: string, mime: string, original_name: string}
     */
    public function consumeReadyToken(int $userId, string $token): array
    {
        $key = $this->readyKey($token);
        $data = Cache::pull($key);
        if (! is_array($data) || ($data['user_id'] ?? null) !== $userId) {
            throw new InvalidArgumentException('Invalid or expired upload token.');
        }

        $path = (string) $data['path'];
        if (! is_file($path)) {
            throw new InvalidArgumentException('Upload file is no longer available.');
        }

        return [
            'path' => $path,
            'mime' => (string) $data['mime'],
            'original_name' => (string) $data['original_name'],
        ];
    }

    private function progressKey(string $uploadId): string
    {
        return self::PROGRESS_PREFIX.$uploadId;
    }

    private function readyKey(string $token): string
    {
        return self::READY_PREFIX.$token;
    }
}
