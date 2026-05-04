<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StoreTraineeRecordedCourseEnrollmentRequest;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\Trainee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class TraineeRecordedCourseEnrollmentsController extends Controller
{
    public function store(
        StoreTraineeRecordedCourseEnrollmentRequest $request,
        Trainee $trainee,
    ): RedirectResponse {
        abort_unless($trainee->is_engineer, 403);

        $courseId = (string) $request->validated('recorded_course_id');
        /** @var RecordedCourse $course */
        $course = RecordedCourse::query()->whereKey($courseId)->firstOrFail();

        if ($trainee->team_id !== $course->team_id) {
            throw ValidationException::withMessages([
                'recorded_course_id' => [__('words.recorded-course-enrollment-team-mismatch')],
            ]);
        }

        $existing = RecordedCourseEnrollment::query()
            ->where('trainee_id', $trainee->id)
            ->where('recorded_course_id', $course->id)
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('back.trainees.show', $trainee)
                ->with('warning', __('words.recorded-course-enrollment-already-exists'));
        }

        RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        return redirect()
            ->route('back.trainees.show', $trainee)
            ->with('success', __('words.recorded-course-enrollment-created'));
    }
}
