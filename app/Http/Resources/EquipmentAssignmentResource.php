<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Equipment\Models\EquipmentAssignment */
class EquipmentAssignmentResource extends JsonResource
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
            'equipment_request_id' => $this->equipment_request_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            'name' => $equipment?->name,
            'category' => $equipment?->category,
            'brand' => $equipment?->brand,
            'model' => $equipment?->model,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'assigned_date' => optional($this->assigned_date)->toDateString(),
            'issue_note' => $this->issue_note,
            'issue_reported_at' => optional($this->issue_reported_at)?->toDateString(),
            'equipment' => $equipment ? [
                'id' => $equipment->id,
                'name' => $equipment->name,
                'category' => $equipment->category,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'description' => $equipment->description,
                'specifications' => $equipment->specifications ?? [],
            ] : null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
