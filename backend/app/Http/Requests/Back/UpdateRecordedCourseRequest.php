<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use App\Models\Back\RecordedCourseLesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRecordedCourseRequest extends FormRequest
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
            'lessons.*.id' => [
                'nullable',
                'uuid',
                Rule::exists('recorded_course_lessons', 'id')->where(
                    fn ($q) => $q->where(
                        'recorded_course_id',
                        $this->route('recorded_course')->id
                    )
                ),
            ],
            'lessons.*.title_ar' => ['required', 'string', 'max:255'],
            'lessons.*.title_en' => ['nullable', 'string', 'max:255'],
            'lessons.*.video' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime',
                'max:'.$maxKb,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lessons = $this->input('lessons', []);
            foreach ($lessons as $index => $lesson) {
                $hasId = ! empty($lesson['id']);
                $hasFile = $this->hasFile("lessons.{$index}.video");
                if (! $hasId && ! $hasFile) {
                    $validator->errors()->add(
                        "lessons.{$index}.video",
                        __('validation.required', ['attribute' => 'video'])
                    );
                }
                if ($hasId) {
                    $model = RecordedCourseLesson::query()->find($lesson['id']);
                    if ($model && ! $model->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION) && ! $hasFile) {
                        $validator->errors()->add(
                            "lessons.{$index}.video",
                            __('validation.required', ['attribute' => 'video'])
                        );
                    }
                }
            }
        });
    }
}
