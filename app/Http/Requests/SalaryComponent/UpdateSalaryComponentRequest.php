<?php

declare(strict_types=1);

namespace App\Http\Requests\SalaryComponent;

use App\Domain\Payroll\Models\SalaryComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in([SalaryComponent::TYPE_EARNING, SalaryComponent::TYPE_DEDUCTION])],
            'calculation_type' => ['sometimes', Rule::in([
                SalaryComponent::CALC_FIXED,
                SalaryComponent::CALC_PERCENT,
                SalaryComponent::CALC_FORMULA,
                SalaryComponent::CALC_TAX,
            ])],
            'default_value' => ['nullable', 'numeric'],
            'formula' => ['nullable', 'string'],
            'base_target' => ['nullable', Rule::in(['BASE', 'GROSS', 'TAXABLE', 'NET'])],
            'is_taxable' => ['nullable', 'boolean'],
            'affects_gross' => ['nullable', 'boolean'],
            'affects_net' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'depends_on' => ['nullable', 'array'],
        ];
    }
}
