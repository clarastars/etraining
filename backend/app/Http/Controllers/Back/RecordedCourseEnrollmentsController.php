<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StoreRecordedCourseEnrollmentRequest;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\Trainee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RecordedCourseEnrollmentsController extends Controller
{
    public function store(StoreRecordedCourseEnrollmentRequest $request, RecordedCourse $recordedCourse): RedirectResponse
    {
        $traineeId = $request->validated('trainee_id');
        $trainee = Trainee::query()->findOrFail($traineeId);

        if ($trainee->team_id !== $recordedCourse->team_id) {
            throw ValidationException::withMessages([
                'trainee_id' => [__('words.recorded-course-enrollment-team-mismatch')],
            ]);
        }

        $existing = RecordedCourseEnrollment::query()
            ->where('trainee_id', $trainee->id)
            ->where('recorded_course_id', $recordedCourse->id)
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('back.settings.recorded-courses.edit', $recordedCourse)
                ->with('warning', __('words.recorded-course-enrollment-already-exists'));
        }

        RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $recordedCourse->id,
            'enrolled_at' => now(),
        ]);

        return redirect()
            ->route('back.settings.recorded-courses.edit', $recordedCourse)
            ->with('success', __('words.recorded-course-enrollment-created'));
    }
}
