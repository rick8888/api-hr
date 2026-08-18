<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Leave\Models\LeaveRequest */
class LeaveRequestResource extends JsonResource
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
            'leave_type' => $this->leave_type,
            'duration_kind' => $this->duration_kind,
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'total_days' => (float) $this->total_days,
            'status' => $this->status,
            'reason' => $this->reason,
            'emergency_contact' => (bool) $this->emergency_contact,
            'traveling_abroad' => (bool) $this->traveling_abroad,
            'document' => $this->document,
            'manager_name' => $this->manager_name,
            'cancel_reason' => $this->cancel_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'submitted_at' => optional($this->created_at)->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
