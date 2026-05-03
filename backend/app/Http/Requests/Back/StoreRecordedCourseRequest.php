<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecordedCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-recorded-courses');
    }

    public function rules(): array
    {
        $maxKb = (int) (config('media-library.max_file_size', 524288000) / 1024);

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unlock_delay_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'allowed_weekdays' => ['required', 'array', 'min:1'],
            'allowed_weekdays.*' => ['integer', 'in:0,1,2,3,4,5,6'],
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.title_ar' => ['required', 'string', 'max:255'],
            'lessons.*.title_en' => ['nullable', 'string', 'max:255'],
            'lessons.*.video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:'.$maxKb,
            ],
        ];
    }
}
