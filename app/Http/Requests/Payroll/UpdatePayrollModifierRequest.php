<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Domain\Payroll\Models\PayrollModifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollModifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in([PayrollModifier::SCOPE_GLOBAL, PayrollModifier::SCOPE_EMPLOYEE])],
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'employee_id' => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'code' => ['sometimes', 'string', 'max:60'],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string'],
            'target' => ['sometimes', Rule::in([
                PayrollModifier::TARGET_BASE,
                PayrollModifier::TARGET_GROSS,
                PayrollModifier::TARGET_TAXABLE,
                PayrollModifier::TARGET_NET,
            ])],
            'type' => ['sometimes', Rule::in([
                PayrollModifier::TYPE_FIXED,
                PayrollModifier::TYPE_PERCENT,
                PayrollModifier::TYPE_FORMULA,
            ])],
            'value' => ['sometimes', 'nullable', 'numeric'],
            'formula' => ['sometimes', 'nullable', 'string'],
            'sign' => ['sometimes', Rule::in(['+', '-'])],
            'period_from' => ['sometimes', 'nullable', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
            'period_to' => ['sometimes', 'nullable', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
