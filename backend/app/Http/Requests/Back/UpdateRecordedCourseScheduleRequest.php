<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecordedCourseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-recorded-courses');
    }

    public function rules(): array
    {
        return [
            'allowed_weekdays' => ['required', 'array', 'min:1'],
            'allowed_weekdays.*' => ['integer', 'in:0,1,2,3,4,5,6'],
        ];
    }
}
