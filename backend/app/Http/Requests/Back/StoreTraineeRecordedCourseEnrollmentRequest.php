<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use App\Models\Back\Trainee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTraineeRecordedCourseEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('manage-recorded-courses')) {
            return false;
        }

        $trainee = $this->route('trainee');

        return $trainee instanceof Trainee && $trainee->is_engineer;
    }

    public function rules(): array
    {
        /** @var Trainee $trainee */
        $trainee = $this->route('trainee');

        return [
            'recorded_course_id' => [
                'required',
                'uuid',
                Rule::exists('recorded_courses', 'id')->where(
                    fn ($query) => $query->where('team_id', $trainee->team_id)
                ),
            ],
        ];
    }
}
