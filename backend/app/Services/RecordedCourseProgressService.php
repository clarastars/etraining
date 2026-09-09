<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\RecordedCourseLesson;
use App\Models\Back\RecordedCourseLessonProgress;
use App\Models\Back\Trainee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordedCourseProgressService
{
    /**
     * First lesson (by sort_order) that does not yet have unlocked_at set (catch-up queue).
     *
     * @return Collection<int, RecordedCourseLesson>
     */
    public function courseForEnrollment(RecordedCourseEnrollment $enrollment): RecordedCourse
    {
        if ($enrollment->relationLoaded('recordedCourse') && $enrollment->recordedCourse !== null) {
            return $enrollment->recordedCourse;
        }

        return RecordedCourse::query()
            ->withoutGlobalScopes()
            ->findOrFail($enrollment->recorded_course_id);
    }

    public function orderedLessons(RecordedCourseEnrollment $enrollment): Collection
    {
        return $this->courseForEnrollment($enrollment)
            ->lessons()
            ->orderBy('sort_order')
            ->get();
    }

    public function progressForLesson(
        RecordedCourseEnrollment $enrollment,
        RecordedCourseLesson $lesson
    ): ?RecordedCourseLessonProgress {
        return RecordedCourseLessonProgress::query()
            ->where('recorded_course_enrollment_id', $enrollment->id)
            ->where('recorded_course_lesson_id', $lesson->id)
            ->first();
    }

    public function nextPendingLesson(RecordedCourseEnrollment $enrollment): ?RecordedCourseLesson
    {
        $lessons = $this->orderedLessons($enrollment);

        foreach ($lessons as $lesson) {
            $progress = $this->progressForLesson($enrollment, $lesson);
            if ($progress === null || $progress->unlocked_at === null) {
                return $lesson;
            }
        }

        return null;
    }

    public function canShowUnlockButton(RecordedCourseEnrollment $enrollment, Carbon $now): bool
    {
        $course = $this->courseForEnrollment($enrollment);
        $allowed = $course->allowed_weekdays ?? [];

        if ($allowed === [] || ! in_array($now->dayOfWeek, $allowed, true)) {
            return false;
        }

        $next = $this->nextPendingLesson($enrollment);
        if ($next === null) {
            return false;
        }

        $lessons = $this->orderedLessons($enrollment);
        $index = $lessons->search(fn (RecordedCourseLesson $l) => $l->id === $next->id);
        if ($index === false || $index === 0) {
            return true;
        }

        /** @var RecordedCourseLesson $previous */
        $previous = $lessons->get($index - 1);
        $prevProgress = $this->progressForLesson($enrollment, $previous);
        if ($prevProgress === null || $prevProgress->completed_at === null) {
            return false;
        }

        $earliestNextUnlock = $prevProgress->completed_at->copy()->addHours((int) $course->unlock_delay_hours);

        return $now->greaterThanOrEqualTo($earliestNextUnlock);
    }

    /**
     * @throws ValidationException
     */
    public function unlockNextLesson(Trainee $trainee, RecordedCourseEnrollment $enrollment, Carbon $now): RecordedCourseLessonProgress
    {
        if ($enrollment->trainee_id !== $trainee->id) {
            abort(403);
        }

        if (! $this->canShowUnlockButton($enrollment, $now)) {
            throw ValidationException::withMessages([
                'unlock' => [__('words.recorded-course-unlock-not-allowed')],
            ]);
        }

        $next = $this->nextPendingLesson($enrollment);
        if ($next === null) {
            throw ValidationException::withMessages([
                'unlock' => [__('words.recorded-course-unlock-not-allowed')],
            ]);
        }

        return DB::transaction(function () use ($enrollment, $next, $now): RecordedCourseLessonProgress {
            /** @var RecordedCourseLessonProgress $progress */
            $progress = RecordedCourseLessonProgress::query()->firstOrCreate(
                [
                    'recorded_course_enrollment_id' => $enrollment->id,
                    'recorded_course_lesson_id' => $next->id,
                ],
                []
            );

            if ($progress->unlocked_at === null) {
                $progress->unlocked_at = $now;
                $progress->save();
            }

            return $progress->fresh();
        });
    }

    /**
     * @throws ValidationException
     */
    public function markLessonComplete(
        Trainee $trainee,
        RecordedCourseEnrollment $enrollment,
        RecordedCourseLesson $lesson,
        Carbon $now
    ): RecordedCourseLessonProgress {
        if ($enrollment->trainee_id !== $trainee->id) {
            abort(403);
        }

        if ($lesson->recorded_course_id !== $enrollment->recorded_course_id) {
            abort(404);
        }

        $progress = $this->progressForLesson($enrollment, $lesson);
        if ($progress === null || $progress->unlocked_at === null) {
            throw ValidationException::withMessages([
                'complete' => [__('words.recorded-course-complete-not-allowed')],
            ]);
        }

        if ($progress->completed_at !== null) {
            return $progress;
        }

        $progress->completed_at = $now;
        $progress->save();

        $this->refreshEnrollmentCompletion($enrollment, $now);

        return $progress->fresh();
    }

    /**
     * When every lesson has completed_at, mark enrollment completed and pending certificate approval.
     */
    public function refreshEnrollmentCompletion(RecordedCourseEnrollment $enrollment, Carbon $now): void
    {
        $lessons = $this->orderedLessons($enrollment);
        if ($lessons->isEmpty()) {
            return;
        }

        foreach ($lessons as $lesson) {
            $progress = $this->progressForLesson($enrollment, $lesson);
            if ($progress === null || $progress->completed_at === null) {
                return;
            }
        }

        $updates = [];
        if ($enrollment->completed_at === null) {
            $updates['completed_at'] = $now;
        }

        if (in_array($enrollment->certificate_status, [
            RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE,
            null,
            '',
        ], true)) {
            $updates['certificate_status'] = RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL;
        }

        if ($updates !== []) {
            $enrollment->fill($updates);
            $enrollment->save();
        }
    }

    public function canStreamLesson(
        Trainee $trainee,
        RecordedCourseEnrollment $enrollment,
        RecordedCourseLesson $lesson
    ): bool {
        if ($enrollment->trainee_id !== $trainee->id) {
            return false;
        }

        if ($lesson->recorded_course_id !== $enrollment->recorded_course_id) {
            return false;
        }

        $progress = $this->progressForLesson($enrollment, $lesson);

        return $progress !== null && $progress->unlocked_at !== null;
    }
}
