<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StoreRecordedCourseLessonRequest;
use App\Http\Requests\Back\UpdateRecordedCourseLessonRequest;
use App\Http\Requests\Back\UpdateRecordedCourseLessonVideoRequest;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseLesson;
use App\Services\RecordedCourseLessonVideoChunkUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RecordedCourseLessonsController extends Controller
{
    public function index(RecordedCourse $recordedCourse): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $recordedCourse->load('lessons');

        return Inertia::render('Back/Settings/RecordedCourses/Lessons/Index', [
            'recordedCourse' => $this->courseSummary($recordedCourse),
            'readiness' => $this->readiness($recordedCourse),
            'lessons' => $recordedCourse->lessons->map(fn (RecordedCourseLesson $lesson) => $this->lessonPayload($recordedCourse, $lesson)),
        ]);
    }

    public function store(StoreRecordedCourseLessonRequest $request, RecordedCourse $recordedCourse): RedirectResponse
    {
        $validated = $request->validated();
        $nextOrder = (int) $recordedCourse->lessons()->max('sort_order') + 1;

        $lesson = $recordedCourse->lessons()->create([
            'sort_order' => $nextOrder,
            'title_ar' => $validated['title_ar'],
            'title_en' => $validated['title_en'] ?? '',
        ]);

        return redirect()
            ->route('back.settings.recorded-courses.lessons.video.edit', [$recordedCourse, $lesson])
            ->with('success', __('words.recorded-course-lesson-created-upload-video'));
    }

    public function update(
        UpdateRecordedCourseLessonRequest $request,
        RecordedCourse $recordedCourse,
        RecordedCourseLesson $lesson,
    ): RedirectResponse {
        $this->ensureLessonBelongsToCourse($recordedCourse, $lesson);

        $validated = $request->validated();
        $lesson->update([
            'title_ar' => $validated['title_ar'],
            'title_en' => $validated['title_en'] ?? '',
        ]);

        return redirect()
            ->route('back.settings.recorded-courses.lessons.index', $recordedCourse)
            ->with('success', __('words.saved-successfully'));
    }

    public function destroy(RecordedCourse $recordedCourse, RecordedCourseLesson $lesson): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $this->ensureLessonBelongsToCourse($recordedCourse, $lesson);

        if ($recordedCourse->lessons()->count() <= 1) {
            return redirect()
                ->route('back.settings.recorded-courses.lessons.index', $recordedCourse)
                ->with('warning', __('words.recorded-course-lesson-min-one'));
        }

        DB::transaction(fn () => $lesson->delete());

        return redirect()
            ->route('back.settings.recorded-courses.lessons.index', $recordedCourse)
            ->with('success', __('words.saved-successfully'));
    }

    public function editVideo(RecordedCourse $recordedCourse, RecordedCourseLesson $lesson): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $this->ensureLessonBelongsToCourse($recordedCourse, $lesson);

        return Inertia::render('Back/Settings/RecordedCourses/Lessons/Video', [
            'recordedCourse' => $this->courseSummary($recordedCourse),
            'readiness' => $this->readiness($recordedCourse->load('lessons')),
            'lesson' => $this->lessonPayload($recordedCourse, $lesson),
        ]);
    }

    public function updateVideo(
        UpdateRecordedCourseLessonVideoRequest $request,
        RecordedCourse $recordedCourse,
        RecordedCourseLesson $lesson,
        RecordedCourseLessonVideoChunkUploadService $chunkUploads,
    ): RedirectResponse {
        $this->ensureLessonBelongsToCourse($recordedCourse, $lesson);

        if ($request->hasFile('video')) {
            $lesson->attachVideo($request->file('video'));
        } elseif (filled($request->input('upload_token'))) {
            try {
                $payload = $chunkUploads->consumeReadyToken(
                    (int) $request->user()->id,
                    (string) $request->input('upload_token')
                );
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'upload_token' => [$e->getMessage()],
                ]);
            }
            $lesson->attachVideoFromAssembledFile($payload['path'], $payload['original_name']);
        }

        return redirect()
            ->route('back.settings.recorded-courses.lessons.video.edit', [$recordedCourse, $lesson])
            ->with('success', __('words.recorded-course-lesson-video-ready'));
    }

    public function stream(RecordedCourse $recordedCourse, RecordedCourseLesson $lesson)
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $this->ensureLessonBelongsToCourse($recordedCourse, $lesson);

        /** @var Media|null $media */
        $media = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION);
        if ($media === null) {
            abort(404);
        }

        if ($media->disk === 's3') {
            $fileUrl = $media->getTemporaryUrl(now()->addMinutes(30), '', [
                'ResponseContentDisposition' => 'inline; filename="'.Str::slug($media->name).'.'.Str::afterLast($media->mime_type, '/').'"',
            ]);

            return redirect()->to($fileUrl);
        }

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type ?? 'video/mp4',
        ]);
    }

    private function ensureLessonBelongsToCourse(RecordedCourse $course, RecordedCourseLesson $lesson): void
    {
        if ($lesson->recorded_course_id !== $course->id) {
            abort(404);
        }
    }

    private function courseSummary(RecordedCourse $course): array
    {
        return [
            'id' => $course->id,
            'name_ar' => $course->name_ar,
            'name_en' => $course->name_en,
        ];
    }

    private function lessonPayload(RecordedCourse $course, RecordedCourseLesson $lesson): array
    {
        $media = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION);

        $titles = $lesson->displayTitles($course->name_ar ?: $course->name_en);

        return [
            'id' => $lesson->id,
            'sort_order' => $lesson->sort_order,
            'title_ar' => $titles['title_ar'],
            'title_en' => $titles['title_en'],
            'has_video' => $media !== null,
            'video_file_name' => $media?->name,
            'video_mime_type' => $media?->mime_type,
            'video_size' => $media?->size,
            'video_stream_url' => $media
                ? route('back.settings.recorded-courses.lessons.stream', [$course, $lesson])
                : null,
        ];
    }

    private function readiness(RecordedCourse $course): array
    {
        $lessons = $course->lessons;
        $withVideo = $lessons->filter(
            fn (RecordedCourseLesson $lesson) => $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION) !== null
        )->count();

        return [
            'details_complete' => filled($course->name_ar) && filled($course->name_en),
            'schedule_complete' => count($course->allowed_weekdays ?? []) > 0,
            'lessons_count' => $lessons->count(),
            'lessons_with_video_count' => $withVideo,
            'all_lessons_have_video' => $lessons->count() > 0 && $withVideo === $lessons->count(),
            'ready_for_engineers' => filled($course->name_ar)
                && filled($course->name_en)
                && count($course->allowed_weekdays ?? []) > 0
                && $lessons->count() > 0
                && $withVideo === $lessons->count(),
            'enrollments_count' => $course->enrollments()->count(),
        ];
    }
}
