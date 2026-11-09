<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Equipment\Models\EquipmentRequest */
class EquipmentRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $equipment = $this->whenLoaded('equipment');
        $employee = $this->whenLoaded('employee');

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'equipment_id' => $this->equipment_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            'equipment_name' => $equipment?->name,
            'quantity' => (int) $this->quantity,
            'priority' => $this->priority,
            'status' => $this->status,
            'reason' => $this->reason,
            'expected_date' => optional($this->expected_date)->toDateString(),
            'request_date' => optional($this->created_at)?->toDateString(),
            'manager_name' => $this->manager_name,
            'reject_reason' => $this->reject_reason,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'equipment' => $equipment ? [
                'id' => $equipment->id,
                'name' => $equipment->name,
                'category' => $equipment->category,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
            ] : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
