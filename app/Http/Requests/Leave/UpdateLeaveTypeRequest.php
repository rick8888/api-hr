<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $leaveTypeId = $this->route('leave_type')?->id ?? $this->route('leave_type');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => [
                'sometimes',
                'string',
                'max:40',
                'alpha_dash',
                Rule::unique('leave_types', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($leaveTypeId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'proof_required' => ['nullable', 'boolean'],
            'is_compensatory_off_type' => ['nullable', 'boolean'],
            'enable_automatic_accrual' => ['nullable', 'boolean'],
            'accrual_frequency' => ['nullable', 'string', Rule::in(LeaveType::ACCRUAL_FREQUENCIES)],
            'accrual_rate' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'max_accrual_limit' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'allow_carry_forward' => ['nullable', 'boolean'],
            'max_carry_forward_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'carry_forward_expiry_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'allow_encashment' => ['nullable', 'boolean'],
            'max_encashment_days' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        $map = [
            'proofRequired' => 'proof_required',
            'isCompensatoryOffType' => 'is_compensatory_off_type',
            'enableAutomaticAccrual' => 'enable_automatic_accrual',
            'accrualFrequency' => 'accrual_frequency',
            'accrualRate' => 'accrual_rate',
            'maxAccrualLimit' => 'max_accrual_limit',
            'allowCarryForward' => 'allow_carry_forward',
            'maxCarryForwardDays' => 'max_carry_forward_days',
            'carryForwardExpiryMonths' => 'carry_forward_expiry_months',
            'allowEncashment' => 'allow_encashment',
            'maxEncashmentDays' => 'max_encashment_days',
            'isActive' => 'active',
        ];

        foreach ($map as $camel => $snake) {
            if ($this->has($camel) && ! $this->has($snake)) {
                $merge[$snake] = $this->input($camel);
            }
        }

        if ($this->has('code') && is_string($this->input('code'))) {
            $merge['code'] = strtoupper(str_replace(' ', '_', trim($this->input('code'))));
        }

        if ($this->has('accrual_frequency') || isset($merge['accrual_frequency'])) {
            $freq = strtolower((string) ($merge['accrual_frequency'] ?? $this->input('accrual_frequency')));
            $merge['accrual_frequency'] = $freq;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
