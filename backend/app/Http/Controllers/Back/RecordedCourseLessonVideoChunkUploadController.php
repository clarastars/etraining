<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StartRecordedCourseVideoChunkUploadRequest;
use App\Services\RecordedCourseLessonVideoChunkUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordedCourseLessonVideoChunkUploadController extends Controller
{
    public function start(
        StartRecordedCourseVideoChunkUploadRequest $request,
        RecordedCourseLessonVideoChunkUploadService $service,
    ): JsonResponse {
        $uploadId = $service->start((int) $request->user()->id, [
            'file_name' => (string) $request->input('file_name'),
            'mime_type' => $request->input('mime_type') ? (string) $request->input('mime_type') : null,
            'total_size' => (int) $request->input('total_size'),
        ]);

        return response()->json(['upload_id' => $uploadId]);
    }

    public function chunk(
        Request $request,
        string $upload,
        RecordedCourseLessonVideoChunkUploadService $service,
    ): JsonResponse {
        abort_unless($request->user()->can('manage-recorded-courses'), 403);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $upload)) {
            abort(404);
        }

        $chunkIndex = (int) $request->header('X-Chunk-Index', -1);
        if ($chunkIndex < 0) {
            abort(422, 'Missing or invalid X-Chunk-Index header.');
        }

        $binary = $request->getContent();
        $service->appendChunk((int) $request->user()->id, $upload, $chunkIndex, $binary);

        return response()->json(['ok' => true]);
    }

    public function complete(
        Request $request,
        string $upload,
        RecordedCourseLessonVideoChunkUploadService $service,
    ): JsonResponse {
        abort_unless($request->user()->can('manage-recorded-courses'), 403);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $upload)) {
            abort(404);
        }

        $token = $service->complete((int) $request->user()->id, $upload);

        return response()->json(['upload_token' => $token]);
    }
}
