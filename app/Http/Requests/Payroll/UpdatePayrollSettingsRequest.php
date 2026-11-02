<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Application\Services\StatutoryComponentSyncService;
use App\Domain\Platform\Models\PayrollCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'statutory_mode' => [
                'sometimes',
                'string',
                Rule::in([
                    StatutoryComponentSyncService::MODE_AUTOMATIC,
                    StatutoryComponentSyncService::MODE_MANUAL,
                ]),
            ],
            'payroll_prefix' => ['sometimes', 'string', 'max:20'],
            'default_pay_period' => ['sometimes', 'string', 'max:80', PayrollCycle::codeRule()],
            'payroll_start_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'payroll_end_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'pay_date_offset_days' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'working_days_per_month' => ['sometimes', 'integer', 'min:1', 'max:31'],
            'lop_deduction_enabled' => ['sometimes', 'boolean'],
            'lop_calculation_method' => ['sometimes', 'string', Rule::in(['working_days', 'calendar_days'])],
            'lop_include_weekends' => ['sometimes', 'boolean'],
            'lop_include_holidays' => ['sometimes', 'boolean'],
            'lop_round_mode' => ['sometimes', 'string', Rule::in(['none', 'half', 'whole'])],
            'expense_reimbursement_enabled' => ['sometimes', 'boolean'],
            'loan_deductions_enabled' => ['sometimes', 'boolean'],
            'auto_generation_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
