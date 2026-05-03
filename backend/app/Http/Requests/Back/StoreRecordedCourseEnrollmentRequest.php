<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use App\Models\Back\RecordedCourse;
use App\Models\Back\Trainee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecordedCourseEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-recorded-courses');
    }

    public function rules(): array
    {
        /** @var RecordedCourse $course */
        $course = $this->route('recorded_course');

        return [
            'trainee_id' => [
                'required',
                'uuid',
                Rule::exists('trainees', 'id')->where(function ($query) use ($course): void {
                    $query->where('team_id', $course->team_id);
                }),
            ],
        ];
    }
}
