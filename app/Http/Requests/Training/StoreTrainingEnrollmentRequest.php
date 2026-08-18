<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'training_id' => ['required', 'integer', 'exists:trainings,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('trainingId') && ! $this->has('training_id')) {
            $this->merge(['training_id' => $this->input('trainingId')]);
        }
    }
}
