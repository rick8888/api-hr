<?php

declare(strict_types=1);

namespace App\Http\Requests\Tax;

use App\Domain\Tax\Models\LegalTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLegalTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('legal_taxes', 'code')
                    ->where(fn ($q) => $q->where('country_id', $this->input('country_id'))),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', Rule::in([
                LegalTax::CATEGORY_SOCIAL,
                LegalTax::CATEGORY_FISCAL,
            ])],
            'type' => ['required', Rule::in([
                LegalTax::TYPE_PERCENT,
                LegalTax::TYPE_PROGRESSIVE,
                LegalTax::TYPE_FIXED,
                LegalTax::TYPE_FORMULA,
            ])],
            'base_target' => ['required', Rule::in(['BASE', 'GROSS', 'TAXABLE', 'NET', 'CUSTOM'])],
            'employer_rate' => ['nullable', 'numeric'],
            'employee_rate' => ['nullable', 'numeric'],
            'ceiling' => ['nullable', 'numeric', 'min:0'],
            'floor' => ['nullable', 'numeric', 'min:0'],
            'formula' => ['nullable', 'string', 'required_if:type,formula'],
            'reduces_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'brackets' => ['nullable', 'array'],
            'brackets.*.min_amount' => ['required_with:brackets', 'numeric', 'min:0'],
            'brackets.*.max_amount' => ['nullable', 'numeric', 'gte:brackets.*.min_amount'],
            'brackets.*.rate' => ['required_with:brackets', 'numeric', 'min:0'],
            'brackets.*.fixed_deduction' => ['nullable', 'numeric'],
            'brackets.*.sequence' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
