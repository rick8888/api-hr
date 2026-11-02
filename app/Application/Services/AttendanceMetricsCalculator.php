<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Attendance\Models\Shift;

final class AttendanceMetricsCalculator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function calculate(array $payload, ?Shift $shift): array
    {
        $checkIn = $this->minutes($payload['check_in'] ?? null);
        $checkOut = $this->minutes($payload['check_out'] ?? null);
        $shiftStart = $this->minutes($shift?->start_time);
        $shiftEnd = $this->minutes($shift?->end_time);
        $overnight = $shiftStart !== null && $shiftEnd !== null && $shiftEnd < $shiftStart;

        if ($overnight && $shiftEnd !== null) {
            $shiftEnd += 1440;
        }

        $normalizedCheckIn = $checkIn;
        if ($overnight && $normalizedCheckIn !== null && $shiftStart !== null && $normalizedCheckIn < $shiftStart) {
            $normalizedCheckIn += 1440;
        }

        $normalizedCheckOut = $checkOut;
        if ($overnight && $normalizedCheckOut !== null && $checkIn !== null && $normalizedCheckOut < $checkIn) {
            $normalizedCheckOut += 1440;
        }

        $payload['late_minutes'] = $normalizedCheckIn !== null && $shiftStart !== null
            ? max(0, $normalizedCheckIn - $shiftStart)
            : 0;
        $payload['early_out_minutes'] = $normalizedCheckOut !== null && $shiftEnd !== null
            ? max(0, $shiftEnd - $normalizedCheckOut)
            : 0;
        $payload['overtime_minutes'] = $normalizedCheckOut !== null && $shiftEnd !== null
            ? max(0, $normalizedCheckOut - $shiftEnd)
            : 0;

        if (($payload['status'] ?? 'on_time') === 'on_time' && $payload['late_minutes'] > 0) {
            $payload['status'] = 'late';
        }

        return $payload;
    }

    private function minutes(mixed $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr((string) $time, 0, 5)));

        return $hours * 60 + $minutes;
    }
}
