<?php

declare(strict_types=1);

namespace App\Http\Requests\Structure;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:160'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'lines' => ['nullable', 'array'],
            'lines.*.salary_component_id' => ['required', 'integer', 'exists:salary_components,id'],
            'lines.*.amount_override' => ['nullable', 'numeric'],
            'lines.*.formula_override' => ['nullable', 'string'],
            'lines.*.rate_override' => ['nullable', 'numeric'],
            'lines.*.sequence' => ['nullable', 'integer', 'min:0'],
            'lines.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
