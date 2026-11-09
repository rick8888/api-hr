<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Payroll\Models\PayrollModifier */
class PayrollModifierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee', fn () => $this->employee);

        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'target' => $this->target,
            'type' => $this->type,
            'value' => (float) $this->value,
            'formula' => $this->formula,
            'sign' => $this->sign,
            'period_from' => $this->period_from,
            'period_to' => $this->period_to,
            'is_active' => (bool) $this->is_active,
            'sequence' => (int) $this->sequence,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'matricule' => $employee->matricule,
                'email' => $employee->email,
                'avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            ] : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
