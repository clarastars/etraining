<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseLesson;
use App\Models\Team;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class ImportRecordedCoursesFromDriveCommand extends Command
{
    protected $signature = 'recorded-courses:import-from-drive
                            {--team= : Team UUID (defaults to TRAINING_DISCLOSURE_DEFAULT_TEAM_ID)}
                            {--parent= : Override parent Drive folder ID}
                            {--folder= : Import only this Drive child folder ID}
                            {--dry-run : List courses/files without downloading or writing}
                            {--force : Re-download and re-attach videos even when size matches}';

    protected $description = 'Import Drive child folders as RecordedCourses; videos (including inside .zip) are stored on S3 for الإفصاح عن التدريب';

    protected GoogleClient $googleClient;

    /** @var list<string> */
    private array $videoExtensions = ['mp4', 'webm', 'mov', 'm4v'];

    /** @var list<string> */
    private array $archiveExtensions = ['zip'];

    public function handle(): int
    {
        $teamId = $this->option('team') ?: config('training-disclosure.default_team_id');
        if (!$teamId) {
            $this->error('Provide --team= or set TRAINING_DISCLOSURE_DEFAULT_TEAM_ID.');

            return 1;
        }

        if (!Team::query()->whereKey($teamId)->exists()) {
            $this->error("Team not found: {$teamId}");

            return 1;
        }

        $parentId = $this->option('parent')
            ?: config('training-disclosure.parent_drive_folder_id');
        if (!$parentId) {
            $this->error('No parent Drive folder ID configured.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        try {
            $this->initializeGoogleClient();
            $service = new GoogleDriveService($this->googleClient);

            $folders = $this->listChildFolders($service, $parentId);
            if ($only = $this->option('folder')) {
                $folders = array_values(array_filter(
                    $folders,
                    fn (array $f): bool => $f['id'] === $only
                ));
                if ($folders === []) {
                    $this->error("Child folder not found under parent: {$only}");

                    return 1;
                }
            }

            if ($folders === []) {
                $this->warn('No child folders found in the parent Drive folder.');

                return 0;
            }

            $this->info('Found '.count($folders).' course folder(s).');
            if ($dryRun) {
                $this->warn('Dry run — no changes will be written.');
            }

            $ok = 0;
            $failed = 0;

            foreach ($folders as $folder) {
                try {
                    $this->importFolder($service, $teamId, $folder, $dryRun, $force);
                    $ok++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("Failed [{$folder['name']}]: {$e->getMessage()}");
                    Log::error('recorded-courses drive import failed', [
                        'folder_id' => $folder['id'],
                        'folder_name' => $folder['name'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Done. courses_ok={$ok} courses_failed={$failed}");

            return $failed > 0 ? 1 : 0;
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());
            Log::error('recorded-courses drive import aborted', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * @param  array{id: string, name: string}  $folder
     */
    private function importFolder(
        GoogleDriveService $service,
        string $teamId,
        array $folder,
        bool $dryRun,
        bool $force
    ): void {
        $this->line('');
        $this->info("Course: {$folder['name']} ({$folder['id']})");

        $discovered = $this->listImportableFilesRecursive($service, $folder['id']);
        $directVideos = array_values(array_filter(
            $discovered,
            fn (array $f): bool => $f['kind'] === 'video'
        ));
        $archives = array_values(array_filter(
            $discovered,
            fn (array $f): bool => $f['kind'] === 'archive'
        ));

        usort($directVideos, fn (array $a, array $b): int => strnatcasecmp($a['relative_path'], $b['relative_path']));
        usort($archives, fn (array $a, array $b): int => strnatcasecmp($a['relative_path'], $b['relative_path']));

        $this->line('  videos: '.count($directVideos));
        $this->line('  archives: '.count($archives));

        if ($dryRun) {
            foreach ($directVideos as $i => $video) {
                $this->line(sprintf(
                    '  [video %d] %s (%s)',
                    $i + 1,
                    $video['relative_path'],
                    $this->formatBytes($video['size'])
                ));
            }
            foreach ($archives as $i => $archive) {
                $this->line(sprintf(
                    '  [zip %d] %s (%s) — will extract videos on import',
                    $i + 1,
                    $archive['relative_path'],
                    $this->formatBytes($archive['size'])
                ));
            }

            return;
        }

        $course = RecordedCourse::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('drive_folder_id', $folder['id'])
            ->first();

        if ($course === null) {
            $course = new RecordedCourse();
            $course->team_id = $teamId;
            $course->drive_folder_id = $folder['id'];
            $course->name_ar = $folder['name'];
            $course->name_en = $folder['name'];
            $course->description = null;
            $course->unlock_delay_hours = (int) config('training-disclosure.unlock_delay_hours', 24);
            $course->allowed_weekdays = config('training-disclosure.allowed_weekdays', [0, 1, 2, 3, 4]);
            $course->save();
            $this->line("  created course {$course->id}");
        } else {
            $course->name_ar = $folder['name'];
            $course->name_en = $folder['name'];
            $course->save();
            $this->line("  updated course {$course->id}");
        }

        $tmpRoot = storage_path('app/tmp/drive-import/'.$course->id);
        if (!is_dir($tmpRoot)) {
            File::makeDirectory($tmpRoot, 0755, true);
        }

        try {
            /** @var list<array{drive_file_id: string, name: string, relative_path: string, size: int, local_path: string|null, source: string}> $lessonSources */
            $lessonSources = [];

            foreach ($directVideos as $video) {
                $lessonSources[] = [
                    'drive_file_id' => $video['id'],
                    'name' => $video['name'],
                    'relative_path' => $video['relative_path'],
                    'size' => $video['size'],
                    'local_path' => null,
                    'source' => 'drive',
                ];
            }

            foreach ($archives as $archive) {
                $extracted = $this->downloadAndExtractArchiveVideos($service, $archive, $tmpRoot);
                foreach ($extracted as $item) {
                    $lessonSources[] = $item;
                }
            }

            usort(
                $lessonSources,
                fn (array $a, array $b): int => strnatcasecmp($a['relative_path'], $b['relative_path'])
            );

            if ($lessonSources === []) {
                $this->warn('  no videos found (direct or inside archives)');
            }

            $seenFileIds = [];
            $lessonFailures = 0;

            foreach ($lessonSources as $index => $source) {
                $seenFileIds[] = $source['drive_file_id'];
                $title = pathinfo($source['name'], PATHINFO_FILENAME) ?: $source['name'];
                $sortOrder = $index + 1;

                try {
                    $lesson = RecordedCourseLesson::query()
                        ->where('recorded_course_id', $course->id)
                        ->where('drive_file_id', $source['drive_file_id'])
                        ->first();

                    if ($lesson === null) {
                        $lesson = RecordedCourseLesson::query()->create([
                            'recorded_course_id' => $course->id,
                            'drive_file_id' => $source['drive_file_id'],
                            'sort_order' => $sortOrder,
                            'title_ar' => $title,
                            'title_en' => $title,
                        ]);
                        $this->line("  + lesson {$sortOrder}: {$title}");
                    } else {
                        $lesson->update([
                            'sort_order' => $sortOrder,
                            'title_ar' => $title,
                            'title_en' => $title,
                        ]);
                    }

                    $media = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION);
                    $expectedSize = (int) $source['size'];

                    if (
                        !$force
                        && $media !== null
                        && $media->disk === 's3'
                        && ($expectedSize === 0 || (int) $media->size === $expectedSize)
                    ) {
                        $this->line("  skip video (size match): {$source['relative_path']}");
                        if ($source['local_path'] && is_file($source['local_path'])) {
                            @unlink($source['local_path']);
                        }
                        continue;
                    }

                    $localPath = $source['local_path'];
                    if ($localPath === null) {
                        $ext = strtolower((string) pathinfo($source['name'], PATHINFO_EXTENSION)) ?: 'mp4';
                        $localPath = $tmpRoot.DIRECTORY_SEPARATOR.Str::uuid().'.'.$ext;
                        $this->line('  get  '.$source['relative_path'].' ('.$this->formatBytes($expectedSize).')');
                        $this->downloadFile($service, $source['drive_file_id'], $localPath);
                    } else {
                        $this->line('  use  '.$source['relative_path'].' ('.$this->formatBytes($expectedSize).')');
                    }

                    try {
                        $lesson->attachVideoFromAssembledFile($localPath, $source['name'], $teamId);
                        $this->line('  s3   attached');
                    } finally {
                        if (is_file($localPath)) {
                            @unlink($localPath);
                        }
                    }
                } catch (\Throwable $e) {
                    $lessonFailures++;
                    $this->error("  lesson failed [{$title}]: {$e->getMessage()}");
                    Log::error('recorded-courses drive import lesson failed', [
                        'course_id' => $course->id,
                        'drive_file_id' => $source['drive_file_id'],
                        'error' => $e->getMessage(),
                    ]);
                    if ($source['local_path'] && is_file($source['local_path'])) {
                        @unlink($source['local_path']);
                    }
                }
            }

            // Remove Drive-sourced lessons that disappeared from Drive
            $orphanQuery = RecordedCourseLesson::query()
                ->where('recorded_course_id', $course->id)
                ->whereNotNull('drive_file_id');

            if ($seenFileIds !== []) {
                $orphanQuery->whereNotIn('drive_file_id', $seenFileIds);
            }

            foreach ($orphanQuery->get() as $orphan) {
                $this->warn("  remove orphan lesson: {$orphan->title_ar}");
                $orphan->delete();
            }

            $course->drive_synced_at = now();
            $course->save();

            if ($lessonFailures > 0) {
                throw new \RuntimeException("{$lessonFailures} lesson(s) failed to import for {$folder['name']}");
            }
        } finally {
            if (is_dir($tmpRoot)) {
                File::deleteDirectory($tmpRoot);
            }
        }
    }

    /**
     * Download a Drive zip and extract video entries to temp files.
     *
     * @param  array{id: string, name: string, relative_path: string, mime_type: string, size: int, kind: string}  $archive
     * @return list<array{drive_file_id: string, name: string, relative_path: string, size: int, local_path: string, source: string}>
     */
    private function downloadAndExtractArchiveVideos(
        GoogleDriveService $service,
        array $archive,
        string $tmpRoot
    ): array {
        $this->line(sprintf(
            '  zip  %s (%s)',
            $archive['relative_path'],
            $this->formatBytes($archive['size'])
        ));

        $zipDir = $tmpRoot.DIRECTORY_SEPARATOR.'zip-'.Str::uuid();
        File::makeDirectory($zipDir, 0755, true);

        $zipPath = $zipDir.DIRECTORY_SEPARATOR.'archive.zip';
        $extractDir = $zipDir.DIRECTORY_SEPARATOR.'extracted';
        File::makeDirectory($extractDir, 0755, true);

        $videos = [];

        try {
            $this->downloadFile($service, $archive['id'], $zipPath);

            $zip = new ZipArchive();
            $opened = $zip->open($zipPath);
            if ($opened !== true) {
                throw new \RuntimeException("Cannot open zip archive (code {$opened}): {$archive['relative_path']}");
            }

            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if ($entryName === false) {
                        continue;
                    }

                    if (!$this->isSafeZipEntry($entryName)) {
                        continue;
                    }

                    $normalizedEntryName = str_replace('\\', '/', $entryName);
                    if (str_ends_with($normalizedEntryName, '/')) {
                        continue;
                    }

                    $baseName = basename($normalizedEntryName);
                    if ($baseName === '' || $baseName === '.' || $baseName === '..') {
                        continue;
                    }

                    $ext = strtolower((string) pathinfo($baseName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $this->videoExtensions, true)) {
                        continue;
                    }

                    $stat = $zip->statIndex($i);
                    $entrySize = (int) ($stat['size'] ?? 0);

                    $localPath = $extractDir.DIRECTORY_SEPARATOR.Str::uuid().'.'.$ext;
                    $stream = $zip->getStream($entryName);
                    if ($stream === false) {
                        throw new \RuntimeException("Cannot read zip entry {$entryName} from {$archive['relative_path']}");
                    }

                    $out = fopen($localPath, 'wb');
                    if ($out === false) {
                        fclose($stream);
                        throw new \RuntimeException("Cannot write extracted video: {$localPath}");
                    }

                    try {
                        while (!feof($stream)) {
                            $chunk = fread($stream, 1024 * 1024);
                            if ($chunk === false || $chunk === '') {
                                break;
                            }
                            fwrite($out, $chunk);
                        }
                    } finally {
                        fclose($stream);
                        fclose($out);
                    }

                    $normalizedEntry = ltrim(str_replace('\\', '/', $entryName), '/');
                    $videos[] = [
                        'drive_file_id' => $archive['id'].'::'.$normalizedEntry,
                        'name' => $baseName,
                        'relative_path' => $archive['relative_path'].'::'.$normalizedEntry,
                        'size' => $entrySize > 0 ? $entrySize : (int) filesize($localPath),
                        'local_path' => $localPath,
                        'source' => 'zip',
                    ];

                    $this->line("    + {$normalizedEntry} (".$this->formatBytes($entrySize).')');
                }
            } finally {
                $zip->close();
            }

            if ($videos === []) {
                $this->warn('    (no video files inside archive)');
            }
        } finally {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
        }

        return $videos;
    }

    private function isSafeZipEntry(string $entryName): bool
    {
        $normalized = str_replace('\\', '/', $entryName);
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, "\0")) {
            return false;
        }
        foreach (explode('/', $normalized) as $part) {
            if ($part === '..') {
                return false;
            }
        }
        if (str_starts_with($normalized, '__MACOSX/') || str_contains($normalized, '/__MACOSX/')) {
            return false;
        }

        return true;
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    private function listChildFolders(GoogleDriveService $service, string $parentId): array
    {
        $folders = [];
        $pageToken = null;

        do {
            $params = [
                'q' => "'{$parentId}' in parents and trashed=false and mimeType='application/vnd.google-apps.folder'",
                'fields' => 'nextPageToken, files(id, name)',
                'pageSize' => 200,
                'orderBy' => 'name',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $results = $service->files->listFiles($params);
            foreach ($results->getFiles() as $file) {
                $folders[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                ];
            }
            $pageToken = $results->getNextPageToken();
        } while ($pageToken);

        return $folders;
    }

    /**
     * @return list<array{id: string, name: string, relative_path: string, mime_type: string, size: int, kind: string}>
     */
    private function listImportableFilesRecursive(
        GoogleDriveService $service,
        string $folderId,
        string $prefix = ''
    ): array {
        $files = [];
        $pageToken = null;

        do {
            $params = [
                'q' => "'{$folderId}' in parents and trashed=false",
                'fields' => 'nextPageToken, files(id, name, size, mimeType)',
                'pageSize' => 1000,
                'orderBy' => 'name',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $results = $service->files->listFiles($params);

            foreach ($results->getFiles() as $file) {
                $name = $file->getName();
                $mime = (string) $file->getMimeType();
                $relative = ltrim($prefix.'/'.$name, '/');

                if ($mime === 'application/vnd.google-apps.folder') {
                    $files = array_merge(
                        $files,
                        $this->listImportableFilesRecursive($service, $file->getId(), $relative)
                    );
                    continue;
                }

                if (strpos($mime, 'application/vnd.google-apps.') === 0) {
                    continue;
                }

                $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
                $kind = $this->detectImportKind($ext, $mime);
                if ($kind === null) {
                    continue;
                }

                $files[] = [
                    'id' => $file->getId(),
                    'name' => $name,
                    'relative_path' => $relative,
                    'mime_type' => $mime,
                    'size' => (int) ($file->getSize() ?? 0),
                    'kind' => $kind,
                ];
            }

            $pageToken = $results->getNextPageToken();
        } while ($pageToken);

        return $files;
    }

    private function detectImportKind(string $ext, string $mime): ?string
    {
        if (in_array($ext, $this->videoExtensions, true) || strpos($mime, 'video/') === 0) {
            return 'video';
        }

        if (in_array($ext, $this->archiveExtensions, true)) {
            return 'archive';
        }

        // Drive sometimes strips extensions; treat zip mime as archive.
        $zipMimes = [
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
            'multipart/x-zip',
        ];
        if (in_array($mime, $zipMimes, true)) {
            return 'archive';
        }

        return null;
    }

    private function downloadFile(GoogleDriveService $service, string $fileId, string $localPath): void
    {
        $tmpPath = $localPath.'.part';
        if (is_file($tmpPath)) {
            @unlink($tmpPath);
        }

        $response = $service->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);
        $body = $response->getBody();
        $handle = fopen($tmpPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open temp file for writing: {$tmpPath}");
        }

        try {
            while (!$body->eof()) {
                $chunk = $body->read(1024 * 1024);
                if ($chunk === '') {
                    break;
                }
                fwrite($handle, $chunk);
            }
        } finally {
            fclose($handle);
        }

        if (!rename($tmpPath, $localPath)) {
            throw new \RuntimeException("Failed to move downloaded file to {$localPath}");
        }
    }

    private function initializeGoogleClient(): void
    {
        $this->googleClient = new GoogleClient();
        $this->googleClient->setApplicationName('ETraining Training Disclosure');
        $this->googleClient->setScopes([GoogleDriveService::DRIVE_READONLY]);

        $credentialsPath = storage_path('app/google-drive-credentials.json');

        if (file_exists($credentialsPath)) {
            $this->googleClient->setAuthConfig($credentialsPath);
            $this->line('Using Google service account credentials from file');
        } else {
            $this->googleClient->setAuthConfig([
                'type' => 'service_account',
                'project_id' => config('services.google.project_id'),
                'private_key_id' => config('services.google.private_key_id'),
                'private_key' => config('services.google.private_key'),
                'client_email' => config('services.google.client_email'),
                'client_id' => config('services.google.client_id'),
                'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => 'https://oauth2.googleapis.com/token',
                'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
                'client_x509_cert_url' => config('services.google.client_x509_cert_url'),
            ]);
            $this->line('Using Google service account credentials from environment');
        }
    }

    private function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = (int) floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), $precision).' '.$units[$pow];
    }
}
