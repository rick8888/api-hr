<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Employee\Models\EmployeeOffboardingTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmployeeOffboardingTask */
class EmployeeOffboardingTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->resource->relationLoaded('employee')
            ? $this->resource->employee
            : null;
        $assignments = $employee !== null && $employee->relationLoaded('equipmentAssignments')
            ? $employee->equipmentAssignments
                ->where('status', 'assigned')
                ->values()
            : collect();

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'matricule' => $employee->matricule,
                'job_title' => $employee->job_title,
                'terminated_at' => optional($employee->terminated_at)->toDateString(),
                'termination' => is_array($employee->metadata['termination'] ?? null)
                    ? $employee->metadata['termination']
                    : null,
                'retirement' => is_array($employee->metadata['retirement'] ?? null)
                    ? $employee->metadata['retirement']
                    : null,
            ] : null,
            'task_key' => $this->task_key,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'equipment_assignments' => $this->task_key === 'return_equipment'
                ? EquipmentAssignmentResource::collection($assignments)
                : [],
        ];
    }
}
