<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use App\Models\Back\RecordedCourse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreRecordedCourseEnrollmentRequest extends FormRequest
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
            'company_id' => [
                'nullable',
                'uuid',
                Rule::exists('companies', 'id')->where(function ($query) use ($course): void {
                    $query->where('team_id', $course->team_id);
                }),
            ],
            'trainee_ids' => ['required', 'array', 'min:1'],
            'trainee_ids.*' => [
                'required',
                'uuid',
                Rule::exists('trainees', 'id')->where(function ($query) use ($course): void {
                    $query->where('team_id', $course->team_id);
                }),
            ],
        ];
    }
}
