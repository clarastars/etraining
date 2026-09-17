<?php

declare(strict_types=1);

namespace App\Http\Requests\Back;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainingDisclosureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-recorded-courses') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'trainees_count' => ['required', 'integer', 'min:1', 'max:5000'],
            'trainees' => ['required', 'array', 'min:1'],
            'trainees.*.name' => ['required', 'string', 'max:255'],
            'trainees.*.phone' => ['nullable', 'string', 'max:50'],
            'trainees.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
