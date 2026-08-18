<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Leave\Models\LeaveType */
class LeaveTypeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'proof_required' => (bool) $this->proof_required,
            'is_compensatory_off_type' => (bool) $this->is_compensatory_off_type,
            'enable_automatic_accrual' => (bool) $this->enable_automatic_accrual,
            'accrual_frequency' => $this->accrual_frequency,
            'accrual_rate' => $this->accrual_rate !== null ? (float) $this->accrual_rate : null,
            'max_accrual_limit' => $this->max_accrual_limit,
            'allow_carry_forward' => (bool) $this->allow_carry_forward,
            'max_carry_forward_days' => $this->max_carry_forward_days,
            'carry_forward_expiry_months' => $this->carry_forward_expiry_months,
            'allow_encashment' => (bool) $this->allow_encashment,
            'max_encashment_days' => $this->max_encashment_days,
            'active' => (bool) $this->is_active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
