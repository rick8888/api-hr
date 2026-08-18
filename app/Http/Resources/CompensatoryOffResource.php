<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Leave\Models\CompensatoryOff */
class CompensatoryOffResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_code' => $employee?->matricule,
            'department' => $employee?->department,
            'worked_date' => optional($this->worked_date)->toDateString(),
            'hours_worked' => (float) $this->hours_worked,
            'comp_off_days' => (float) $this->comp_off_days,
            'expiry_date' => optional($this->expiry_date)->toDateString(),
            'status' => $this->status,
            'usage_status' => $this->usage_status,
            'reason' => $this->reason,
            'manager_name' => $this->manager_name,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
