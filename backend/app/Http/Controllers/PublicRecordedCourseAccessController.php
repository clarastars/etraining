<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\RecordedCourseLesson;
use App\Services\RecordedCourseProgressService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PublicRecordedCourseAccessController extends Controller
{
    public function __construct(
        private readonly RecordedCourseProgressService $progressService
    ) {
    }

    private function enrollmentByToken(string $token): RecordedCourseEnrollment
    {
        return RecordedCourseEnrollment::query()
            ->where('access_token', $token)
            ->with([
                'trainee',
                'recordedCourse' => function ($q): void {
                    $q->withoutGlobalScopes()->with(['lessons' => function ($q2): void {
                        $q2->orderBy('sort_order');
                    }]);
                },
            ])
            ->firstOrFail();
    }

    private function abortUnlessCheckedIn(RecordedCourseEnrollment $enrollment): void
    {
        if (! $enrollment->isCheckedIn()) {
            abort(403, __('words.recorded-course-check-in-required'));
        }
    }

    public function show(string $token): View
    {
        $enrollment = $this->enrollmentByToken($token);
        $course = $enrollment->recordedCourse;
        $trainee = $enrollment->trainee;
        $now = Carbon::now(config('app.timezone'));

        $lessons = $course->lessons->map(function (RecordedCourseLesson $lesson) use ($enrollment, $trainee, $token): array {
            $progress = $this->progressService->progressForLesson($enrollment, $lesson);
            $unlocked = $progress?->unlocked_at;
            $completed = $progress?->completed_at;
            $canStream = $enrollment->isCheckedIn()
                && $this->progressService->canStreamLesson($trainee, $enrollment, $lesson);
            $hasVideo = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION) !== null;

            return [
                'id' => $lesson->id,
                'title_ar' => $lesson->title_ar,
                'title_en' => $lesson->title_en,
                'sort_order' => $lesson->sort_order,
                'unlocked_at' => $unlocked,
                'completed_at' => $completed,
                'can_stream' => $canStream && $hasVideo,
                'stream_url' => $canStream && $hasVideo
                    ? route('recorded-courses.public.stream', ['token' => $token, 'lesson' => $lesson->id])
                    : null,
            ];
        });

        $firstStreamable = $lessons->first(fn (array $l) => $l['can_stream'] && $l['stream_url']);

        return view('recorded-courses.public.show', [
            'token' => $token,
            'enrollment' => $enrollment,
            'course' => $course,
            'trainee' => $trainee,
            'lessons' => $lessons,
            'firstStreamable' => $firstStreamable,
            'checkedIn' => $enrollment->isCheckedIn(),
            'canUnlock' => $enrollment->isCheckedIn()
                && $this->progressService->canShowUnlockButton($enrollment, $now),
            'nextPendingLesson' => $this->progressService->nextPendingLesson($enrollment),
        ]);
    }

    public function checkIn(Request $request, string $token): RedirectResponse
    {
        $enrollment = $this->enrollmentByToken($token);

        if ($enrollment->checked_in_at === null) {
            $enrollment->checked_in_at = now();
            $enrollment->save();
        }

        return redirect()->route('recorded-courses.public.show', ['token' => $token]);
    }

    public function checkOut(Request $request, string $token): RedirectResponse
    {
        $enrollment = $this->enrollmentByToken($token);
        $this->abortUnlessCheckedIn($enrollment);

        $enrollment->checked_out_at = now();
        $enrollment->save();

        return redirect()->route('recorded-courses.public.show', ['token' => $token]);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $enrollment = $this->enrollmentByToken($token);
        $this->abortUnlessCheckedIn($enrollment);
        $now = Carbon::now(config('app.timezone'));

        $this->progressService->unlockNextLesson($enrollment->trainee, $enrollment, $now);

        return redirect()->route('recorded-courses.public.show', ['token' => $token]);
    }

    public function complete(Request $request, string $token, string $lesson): RedirectResponse
    {
        $enrollment = $this->enrollmentByToken($token);
        $this->abortUnlessCheckedIn($enrollment);

        $lessonModel = RecordedCourseLesson::query()->findOrFail($lesson);
        $now = Carbon::now(config('app.timezone'));

        $this->progressService->markLessonComplete(
            $enrollment->trainee,
            $enrollment,
            $lessonModel,
            $now
        );

        return redirect()->route('recorded-courses.public.show', ['token' => $token]);
    }

    public function stream(string $token, string $lesson)
    {
        $enrollment = $this->enrollmentByToken($token);
        $this->abortUnlessCheckedIn($enrollment);

        $lessonModel = RecordedCourseLesson::query()->findOrFail($lesson);

        if (! $this->progressService->canStreamLesson($enrollment->trainee, $enrollment, $lessonModel)) {
            abort(403);
        }

        /** @var Media|null $media */
        $media = $lessonModel->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION);
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
}
