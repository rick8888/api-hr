<?php

declare(strict_types=1);

namespace App\Http\Requests\SelfService;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSelfServiceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other', 'Male', 'Female', 'Other'])],
            'language' => ['nullable', 'string', 'max:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('firstName')) {
            $merge['first_name'] = $this->input('firstName');
        }
        if ($this->has('lastName')) {
            $merge['last_name'] = $this->input('lastName');
        }
        if ($this->has('dateOfBirth')) {
            $merge['date_of_birth'] = $this->input('dateOfBirth');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
