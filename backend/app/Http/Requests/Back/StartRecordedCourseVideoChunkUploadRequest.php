<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use Illuminate\Foundation\Http\FormRequest;

class StartRecordedCourseVideoChunkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-recorded-courses');
    }

    public function rules(): array
    {
        $maxBytes = (int) config('media-library.max_file_size', 524_288_000);

        return [
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
        ];
    }
}
