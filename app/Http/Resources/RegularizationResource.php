<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Attendance\Models\Regularization */
class RegularizationResource extends JsonResource
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
            'employee_avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            'department' => $employee?->department,
            'attendance_date' => optional($this->attendance_date)->toDateString(),
            'request_date' => optional($this->created_at)->toDateString(),
            'type' => $this->type,
            'status' => $this->status,
            'check_in' => $this->formatTime($this->check_in),
            'check_out' => $this->formatTime($this->check_out),
            'reason' => $this->reason,
            'attachment_count' => (int) $this->attachment_count,
            'manager_name' => $this->manager_name,
            'reviewed_at' => optional($this->reviewed_at)?->toDateString(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function formatTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return substr($value, 0, 5);
        }

        return null;
    }
}
