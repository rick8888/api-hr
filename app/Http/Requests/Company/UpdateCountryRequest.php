<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['super_admin'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $countryId = $this->route('country')?->id ?? $this->route('country');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:3',
                'alpha_num',
                Rule::unique('countries', 'code')->ignore($countryId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'alpha'],
            'locale' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }
    }
}
