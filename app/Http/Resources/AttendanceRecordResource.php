<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Attendance\Models\AttendanceBreak;
use App\Domain\Attendance\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceRecord */
class AttendanceRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $employee = $this->resource->relationLoaded('employee')
            ? $this->resource->employee
            : null;
        $worked = $this->workedMinutes();

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : null,
            'employee_avatar_url' => EmployeeAvatarUrl::for($employee, $request),
            'employee_code' => $employee?->matricule,
            'date' => $this->attendance_date?->toDateString(),
            'shift_id' => $this->shift_id,
            'shift_name' => $this->shift?->name,
            'site_id' => $this->site_id,
            'location' => $this->site?->name,
            'check_in' => $this->time($this->check_in),
            'check_in_at' => $this->checkInTimestamp(),
            'check_out' => $this->time($this->check_out),
            'working_minutes' => $worked,
            'late_minutes' => (int) $this->late_minutes,
            'early_out_minutes' => (int) $this->early_out_minutes,
            'overtime_minutes' => (int) $this->overtime_minutes,
            'break_minutes' => $this->totalBreakMinutes(),
            'recorded_break_minutes' => $this->completedBreakMinutes(),
            'is_on_break' => $this->activeBreak() !== null,
            'active_break_started_at' => $this->activeBreak()?->started_at?->toIso8601String(),
            'breaks' => $this->whenLoaded('breaks', fn () => $this->resource->breaks
                ->map(fn (AttendanceBreak $break) => [
                    'id' => $break->id,
                    'started_at' => $break->started_at?->toIso8601String(),
                    'ended_at' => $break->ended_at?->toIso8601String(),
                    'duration_minutes' => $this->breakDuration($break),
                ])
                ->values()),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function time(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 5);
    }

    private function checkInTimestamp(): ?string
    {
        if ($this->attendance_date === null || $this->check_in === null || $this->check_in === '') {
            return null;
        }

        return Carbon::parse(
            $this->attendance_date->toDateString().' '.substr((string) $this->check_in, 0, 8),
            config('app.timezone'),
        )->toIso8601String();
    }

    private function workedMinutes(): int
    {
        $in = $this->minutes($this->check_in);
        $out = $this->minutes($this->check_out);

        if ($in === null || $out === null) {
            return 0;
        }

        $shiftStart = $this->minutes($this->shift?->start_time);
        $shiftEnd = $this->minutes($this->shift?->end_time);
        $overnightShift = $shiftStart !== null && $shiftEnd !== null && $shiftEnd < $shiftStart;

        if ($out < $in && ($overnightShift || $this->shift_id === null)) {
            $out += 1440;
        }

        return max(0, $out - $in);
    }

    private function totalBreakMinutes(): int
    {
        if (! $this->resource->relationLoaded('breaks')) {
            return 0;
        }

        return $this->resource->breaks->sum(
            fn (AttendanceBreak $break) => $this->breakDuration($break)
        );
    }

    private function completedBreakMinutes(): int
    {
        if (! $this->resource->relationLoaded('breaks')) {
            return 0;
        }

        return $this->resource->breaks
            ->whereNotNull('ended_at')
            ->sum(fn (AttendanceBreak $break) => (int) $break->duration_minutes);
    }

    private function activeBreak(): ?AttendanceBreak
    {
        if (! $this->resource->relationLoaded('breaks')) {
            return null;
        }

        return $this->resource->breaks->first(
            fn (AttendanceBreak $break) => $break->ended_at === null
        );
    }

    private function breakDuration(AttendanceBreak $break): int
    {
        if ($break->ended_at !== null) {
            return (int) $break->duration_minutes;
        }

        return $break->started_at === null
            ? 0
            : max(0, (int) $break->started_at->diffInMinutes(now()));
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
