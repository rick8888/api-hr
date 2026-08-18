<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Domain\Payroll\Models\PayrollModifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollModifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in([PayrollModifier::SCOPE_GLOBAL, PayrollModifier::SCOPE_EMPLOYEE])],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
                'required_if:scope,'.PayrollModifier::SCOPE_EMPLOYEE,
            ],
            'code' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'target' => ['required', Rule::in([
                PayrollModifier::TARGET_BASE,
                PayrollModifier::TARGET_GROSS,
                PayrollModifier::TARGET_TAXABLE,
                PayrollModifier::TARGET_NET,
            ])],
            'type' => ['required', Rule::in([
                PayrollModifier::TYPE_FIXED,
                PayrollModifier::TYPE_PERCENT,
                PayrollModifier::TYPE_FORMULA,
            ])],
            'value' => ['nullable', 'numeric'],
            'formula' => ['nullable', 'string', 'required_if:type,'.PayrollModifier::TYPE_FORMULA],
            'sign' => ['required', Rule::in(['+', '-'])],
            'period_from' => ['nullable', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
            'period_to' => ['nullable', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
            'is_active' => ['nullable', 'boolean'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
