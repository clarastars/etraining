<?php

declare(strict_types=1);

namespace App\Services;

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
    public function orderedLessons(RecordedCourseEnrollment $enrollment): Collection
    {
        return $enrollment->recordedCourse
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
        $course = $enrollment->recordedCourse;
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

        return $progress->fresh();
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
