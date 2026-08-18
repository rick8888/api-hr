<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Attendance\Models\AttendanceRecord */
class AttendanceRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->whenLoaded('employee');
        $worked = $this->workedMinutes();

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_code' => $employee?->matricule,
            'date' => $this->attendance_date?->toDateString(),
            'shift_id' => $this->shift_id,
            'shift_name' => $this->shift?->name,
            'site_id' => $this->site_id,
            'location' => $this->site?->name,
            'check_in' => $this->time($this->check_in),
            'check_out' => $this->time($this->check_out),
            'working_minutes' => $worked,
            'late_minutes' => (int) $this->late_minutes,
            'early_out_minutes' => (int) $this->early_out_minutes,
            'overtime_minutes' => (int) $this->overtime_minutes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function time(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 5);
    }

    private function workedMinutes(): int
    {
        $in = $this->minutes($this->check_in);
        $out = $this->minutes($this->check_out);

        return $in !== null && $out !== null ? max(0, $out - $in) : 0;
    }

    private function minutes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        [$hours, $minutes] = array_map('intval', explode(':', substr((string) $value, 0, 5)));

        return $hours * 60 + $minutes;
    }
}
