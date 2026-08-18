<?php

declare(strict_types=1);

namespace App\Http\Requests\SalaryComponent;

use App\Domain\Payroll\Models\SalaryComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in([SalaryComponent::TYPE_EARNING, SalaryComponent::TYPE_DEDUCTION])],
            'calculation_type' => ['required', Rule::in([
                SalaryComponent::CALC_FIXED,
                SalaryComponent::CALC_PERCENT,
                SalaryComponent::CALC_FORMULA,
                SalaryComponent::CALC_TAX,
            ])],
            'default_value' => ['nullable', 'numeric'],
            'formula' => ['nullable', 'string', 'required_if:calculation_type,formula'],
            'base_target' => ['nullable', Rule::in(['BASE', 'GROSS', 'TAXABLE', 'NET'])],
            'is_taxable' => ['nullable', 'boolean'],
            'affects_gross' => ['nullable', 'boolean'],
            'affects_net' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_statutory' => ['nullable', 'boolean'],
            'legal_tax_id' => ['nullable', 'integer', 'exists:legal_taxes,id'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'depends_on' => ['nullable', 'array'],
            'depends_on.*' => ['string', 'max:60'],
        ];
    }
}
