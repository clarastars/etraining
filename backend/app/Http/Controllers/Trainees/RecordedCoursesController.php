<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trainees;

use App\Http\Controllers\Controller;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\RecordedCourseLesson;
use App\Models\Back\Trainee;
use App\Services\RecordedCourseProgressService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RecordedCoursesController extends Controller
{
    public function __construct(
        private readonly RecordedCourseProgressService $progressService
    ) {
    }

    private function traineeForUser(): Trainee
    {
        $trainee = Trainee::query()->where('user_id', auth()->id())->first();
        if ($trainee === null) {
            abort(403);
        }

        return $trainee;
    }

    private function enrollmentForTrainee(Trainee $trainee, string $enrollmentId): RecordedCourseEnrollment
    {
        return RecordedCourseEnrollment::query()
            ->where('trainee_id', $trainee->id)
            ->whereKey($enrollmentId)
            ->with(['recordedCourse' => function ($q): void {
                $q->with(['lessons' => function ($q2): void {
                    $q2->orderBy('sort_order');
                }]);
            }])
            ->firstOrFail();
    }

    public function index(): InertiaResponse
    {
        $trainee = $this->traineeForUser();

        $enrollments = RecordedCourseEnrollment::query()
            ->where('trainee_id', $trainee->id)
            ->with('recordedCourse')
            ->latest('enrolled_at')
            ->get()
            ->map(function (RecordedCourseEnrollment $e): array {
                $course = $e->recordedCourse;
                $now = Carbon::now(config('app.timezone'));

                return [
                    'id' => $e->id,
                    'enrolled_at' => $e->enrolled_at?->toIso8601String(),
                    'course' => [
                        'id' => $course->id,
                        'name_ar' => $course->name_ar,
                        'name_en' => $course->name_en,
                    ],
                    'can_unlock_today' => $this->progressService->canShowUnlockButton($e, $now),
                    'has_pending_lesson' => $this->progressService->nextPendingLesson($e) !== null,
                ];
            });

        return Inertia::render('Trainees/RecordedCourses/Index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function show(string $enrollmentId): InertiaResponse
    {
        $trainee = $this->traineeForUser();
        $enrollment = $this->enrollmentForTrainee($trainee, $enrollmentId);
        $now = Carbon::now(config('app.timezone'));

        $course = $enrollment->recordedCourse;
        $lessons = $course->lessons;

        $lessonPayload = $lessons->map(function (RecordedCourseLesson $lesson) use ($enrollment, $trainee): array {
            $progress = $this->progressService->progressForLesson($enrollment, $lesson);
            $unlocked = $progress?->unlocked_at;
            $completed = $progress?->completed_at;
            $canStream = $this->progressService->canStreamLesson($trainee, $enrollment, $lesson);
            $hasVideo = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION) !== null;

            return [
                'id' => $lesson->id,
                'title_ar' => $lesson->title_ar,
                'title_en' => $lesson->title_en,
                'sort_order' => $lesson->sort_order,
                'unlocked_at' => $unlocked?->toIso8601String(),
                'completed_at' => $completed?->toIso8601String(),
                'can_stream' => $canStream && $hasVideo,
                'stream_url' => $canStream && $hasVideo
                    ? route('recorded-courses.enrollments.lessons.stream', [$enrollment->id, $lesson->id])
                    : null,
            ];
        });

        $next = $this->progressService->nextPendingLesson($enrollment);

        return Inertia::render('Trainees/RecordedCourses/Show', [
            'enrollment' => [
                'id' => $enrollment->id,
                'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
            ],
            'course' => [
                'id' => $course->id,
                'name_ar' => $course->name_ar,
                'name_en' => $course->name_en,
                'allowed_weekdays' => $course->allowed_weekdays ?? [],
                'unlock_delay_hours' => (int) $course->unlock_delay_hours,
            ],
            'lessons' => $lessonPayload,
            'next_pending_lesson_id' => $next?->id,
            'can_unlock_today' => $this->progressService->canShowUnlockButton($enrollment, $now),
        ]);
    }

    public function unlock(string $enrollmentId): RedirectResponse
    {
        $trainee = $this->traineeForUser();
        $enrollment = $this->enrollmentForTrainee($trainee, $enrollmentId);
        $now = Carbon::now(config('app.timezone'));

        $this->progressService->unlockNextLesson($trainee, $enrollment, $now);

        return redirect()->route('recorded-courses.enrollments.show', $enrollment->id);
    }

    public function complete(string $enrollmentId, string $lessonId): RedirectResponse
    {
        $trainee = $this->traineeForUser();
        $enrollment = $this->enrollmentForTrainee($trainee, $enrollmentId);
        $lesson = RecordedCourseLesson::query()->findOrFail($lessonId);
        $now = Carbon::now(config('app.timezone'));

        $this->progressService->markLessonComplete($trainee, $enrollment, $lesson, $now);

        return redirect()->route('recorded-courses.enrollments.show', $enrollment->id);
    }

    public function stream(string $enrollmentId, string $lessonId)
    {
        $trainee = $this->traineeForUser();
        $enrollment = $this->enrollmentForTrainee($trainee, $enrollmentId);
        $lesson = RecordedCourseLesson::query()->findOrFail($lessonId);

        if (! $this->progressService->canStreamLesson($trainee, $enrollment, $lesson)) {
            abort(403);
        }

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
}
