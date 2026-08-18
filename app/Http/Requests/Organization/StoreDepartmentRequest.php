<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('departments', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'parent_department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parentId')) {
            $this->merge([
                'parent_department_id' => $this->input('parentId'),
            ]);
        }
    }
}
