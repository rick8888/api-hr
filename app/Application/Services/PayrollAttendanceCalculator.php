<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Agrège présence / absences / congés d'un employé sur une période de paie
 * et calcule le salaire de base proratisé.
 */
final class PayrollAttendanceCalculator
{
    private const PAID_LEAVE = [
        'casual_leave',
        'sick_leave',
        'maternity_leave',
        'paternity_leave',
        'voting_leave',
        'annual_leave',
        'paid_leave',
        'conges',
        'cp',
    ];

    private const WORKED_AS_LEAVE = [
        'work_from_home',
    ];

    /**
     * @return array{
     *   working_days: float,
     *   days_worked: float,
     *   absent_days: float,
     *   leave_days: float,
     *   unpaid_leave_days: float,
     *   unpaid_days: float,
     *   recorded_absent_days: float,
     *   pending_attendance_days: float,
     *   no_show_days: float,
     *   payable_days: float,
     *   overtime_hours: float,
     *   late_days: float,
     *   early_checkout_days: float,
     *   overtime_days: float,
     *   holidays: float,
     *   daily_rate: float,
     *   contractual_salary: float,
     *   payable_salary: float
     * }
     */
    public function summarize(
        Employee $employee,
        string $periodStart,
        string $periodEnd,
        float $contractualBase,
        bool $assumeUnrecordedDaysPayable = false,
    ): array {
        $periodFrom = Carbon::parse($periodStart)->startOfDay();
        $periodTo = Carbon::parse($periodEnd)->endOfDay();
        $from = $periodFrom->copy();
        $to = $periodTo->copy();

        if ($employee->hired_at && $employee->hired_at->gt($from)) {
            $from = $employee->hired_at->copy()->startOfDay();
        }
        if ($employee->terminated_at && $employee->terminated_at->lt($to)) {
            $to = $employee->terminated_at->copy()->endOfDay();
        }

        $company = $employee->company;
        $settings = $company ? PayrollSettingsService::bag($company) : [];
        $includeWeekends = (bool) ($settings['lop_include_weekends'] ?? false);
        $includeHolidays = (bool) ($settings['lop_include_holidays'] ?? false);
        $roundMode = (string) ($settings['lop_round_mode'] ?? 'none');

        $holidayDates = $company ? $this->holidayDates($company, $periodFrom, $periodTo) : [];
        $records = $this->attendanceByDate($employee, $from, $to);
        $leaveByDate = $this->leaveByDate($employee, $from, $to);

        $workingDays = 0.0;
        $daysWorked = 0.0;
        $absentDays = 0.0;
        $recordedAbsentDays = 0.0;
        $leaveDays = 0.0;
        $unpaidLeaveDays = 0.0;
        $noShowDays = 0.0;
        $pendingAttendanceDays = 0.0;
        $overtimeMinutes = 0;
        $lateDays = 0.0;
        $earlyCheckoutDays = 0.0;
        $overtimeDays = 0.0;

        foreach (CarbonPeriod::create($periodFrom->toDateString(), $periodTo->toDateString()) as $day) {
            /** @var Carbon $day */
            $iso = $day->toDateString();
            if ($this->isExpectedWorkingDay(
                $day,
                $includeWeekends,
                $includeHolidays,
                isset($holidayDates[$iso]),
            )) {
                $workingDays += 1.0;
            }
        }

        if ($from->lte($to)) {
            foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
                /** @var Carbon $day */
                $iso = $day->toDateString();
                $isHoliday = isset($holidayDates[$iso]);
                if (! $this->isExpectedWorkingDay($day, $includeWeekends, $includeHolidays, $isHoliday)) {
                    continue;
                }

                $record = $records[$iso] ?? null;
                $leave = $leaveByDate[$iso] ?? null;

                if ($record !== null) {
                    $overtimeMinutes += (int) ($record->overtime_minutes ?? 0);
                    $lateDays += (int) ($record->late_minutes ?? 0) > 0 ? 1.0 : 0.0;
                    $earlyCheckoutDays += (int) ($record->early_out_minutes ?? 0) > 0 ? 1.0 : 0.0;
                    $overtimeDays += (int) ($record->overtime_minutes ?? 0) > 0 ? 1.0 : 0.0;
                    $credit = $this->creditFromRecord($record, $leave);
                    $daysWorked += $credit['worked'];
                    $leaveDays += $credit['paid_leave'];
                    $unpaidLeaveDays += $credit['unpaid_leave'];
                    $absentDays += $credit['absent'];
                    $recordedAbsentDays += $credit['absent'];

                    continue;
                }

                if ($leave !== null) {
                    if ($leave['kind'] === 'worked') {
                        $daysWorked += $leave['days'];
                    } elseif ($leave['kind'] === 'paid') {
                        $leaveDays += $leave['days'];
                    } else {
                        $unpaidLeaveDays += $leave['days'];
                    }

                    $uncoveredDays = max(0.0, 1.0 - $leave['days']);
                    if ($uncoveredDays <= 0) {
                        continue;
                    }
                    if ($assumeUnrecordedDaysPayable && $day->greaterThanOrEqualTo(Carbon::today())) {
                        $pendingAttendanceDays += $uncoveredDays;
                    } else {
                        $noShowDays += $uncoveredDays;
                        $absentDays += $uncoveredDays;
                    }

                    continue;
                }

                if ($assumeUnrecordedDaysPayable) {
                    if ($day->greaterThanOrEqualTo(Carbon::today())) {
                        $pendingAttendanceDays += 1.0;
                    } else {
                        $noShowDays += 1.0;
                        $absentDays += 1.0;
                    }
                } else {
                    $noShowDays += 1.0;
                    $absentDays += 1.0;
                }
            }
        }

        $payableDays = $this->roundDays(
            $daysWorked + $leaveDays + $pendingAttendanceDays,
            $roundMode,
        );
        $unpaidDays = $this->roundDays($absentDays + $unpaidLeaveDays, $roundMode);
        $workingDays = $this->roundDays($workingDays, $roundMode);

        if ($workingDays <= 0) {
            $dailyRate = 0.0;
            $payableSalary = 0.0;
        } else {
            $dailyRate = round($contractualBase / $workingDays, 4);
            $payableSalary = round($dailyRate * $payableDays, 2);
        }

        return [
            'working_days' => $workingDays,
            'days_worked' => round($daysWorked, 2),
            'absent_days' => round($absentDays, 2),
            'leave_days' => round($leaveDays, 2),
            'unpaid_leave_days' => round($unpaidLeaveDays, 2),
            'recorded_absent_days' => round($recordedAbsentDays, 2),
            'pending_attendance_days' => round($pendingAttendanceDays, 2),
            'no_show_days' => round($noShowDays, 2),
            'unpaid_days' => $unpaidDays,
            'payable_days' => $payableDays,
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'late_days' => $lateDays,
            'early_checkout_days' => $earlyCheckoutDays,
            'overtime_days' => $overtimeDays,
            'holidays' => (float) count($holidayDates),
            'daily_rate' => $dailyRate,
            'contractual_salary' => round($contractualBase, 2),
            'payable_salary' => $payableSalary,
        ];
    }

    private function isExpectedWorkingDay(Carbon $day, bool $includeWeekends, bool $includeHolidays, bool $isHoliday): bool
    {
        if ($day->isWeekend() && ! $includeWeekends) {
            return false;
        }
        if ($isHoliday && ! $includeHolidays) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{kind: string, days: float}|null  $leave
     * @return array{worked: float, paid_leave: float, unpaid_leave: float, absent: float}
     */
    private function creditFromRecord(AttendanceRecord $record, ?array $leave): array
    {
        $out = ['worked' => 0.0, 'paid_leave' => 0.0, 'unpaid_leave' => 0.0, 'absent' => 0.0];
        $status = (string) $record->status;

        if (in_array($status, ['on_time', 'late'], true)) {
            $out['worked'] = 1.0;

            return $out;
        }
        if ($status === 'half_day') {
            $out['worked'] = 0.5;
            $creditedLeaveDays = $this->creditLeave($out, $leave, 0.5);
            $out['absent'] = max(0.0, 0.5 - $creditedLeaveDays);

            return $out;
        }
        if (in_array($status, ['leave', 'absent'], true)) {
            $creditedLeaveDays = $this->creditLeave($out, $leave, 1.0);
            $out['absent'] = max(0.0, 1.0 - $creditedLeaveDays);

            return $out;
        }

        $out['absent'] = 1.0;

        return $out;
    }

    /**
     * @param  array{worked: float, paid_leave: float, unpaid_leave: float, absent: float}  $out
     * @param  array{kind: string, days: float}|null  $leave
     */
    private function creditLeave(array &$out, ?array $leave, float $maximumDays): float
    {
        if ($leave === null) {
            return 0.0;
        }

        $days = min($maximumDays, $leave['days']);
        if ($leave['kind'] === 'worked') {
            $out['worked'] += $days;
        } elseif ($leave['kind'] === 'unpaid') {
            $out['unpaid_leave'] += $days;
        } else {
            $out['paid_leave'] += $days;
        }

        return $days;
    }

    /** @return array<string, AttendanceRecord> */
    private function attendanceByDate(Employee $employee, Carbon $from, Carbon $to): array
    {
        $rows = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $iso = optional($row->attendance_date)->toDateString();
            if ($iso) {
                $byDate[$iso] = $row;
            }
        }

        return $byDate;
    }

    /** @return array<string, array{kind: string, days: float}> */
    private function leaveByDate(Employee $employee, Carbon $from, Carbon $to): array
    {
        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();

        $byDate = [];
        foreach ($requests as $request) {
            $kind = $this->leaveKind((string) $request->leave_type);
            $perDay = $request->duration_kind === 'half' ? 0.5 : 1.0;
            $start = Carbon::parse($request->start_date)->max($from);
            $end = Carbon::parse($request->end_date)->min($to);
            foreach (CarbonPeriod::create($start->toDateString(), $end->toDateString()) as $day) {
                $iso = $day->toDateString();
                $existing = $byDate[$iso]['days'] ?? 0.0;
                $byDate[$iso] = [
                    'kind' => $kind,
                    'days' => min(1.0, $existing + $perDay),
                ];
            }
        }

        return $byDate;
    }

    private function leaveKind(string $code): string
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $code));
        if (in_array($normalized, self::WORKED_AS_LEAVE, true) || str_contains($normalized, 'home')) {
            return 'worked';
        }
        if (
            str_contains($normalized, 'unpaid')
            || str_contains($normalized, 'sans_solde')
            || str_contains($normalized, 'lop')
            || $normalized === 'emergency_leave'
        ) {
            return 'unpaid';
        }
        if (in_array($normalized, self::PAID_LEAVE, true) || str_contains($normalized, 'conge')) {
            return 'paid';
        }

        return 'paid';
    }

    /** @return array<string, true> */
    private function holidayDates(Company $company, Carbon $from, Carbon $to): array
    {
        $rows = Holiday::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $dates = [];
        foreach ($rows as $holiday) {
            if ($holiday->date === null) {
                continue;
            }
            if ($holiday->recurring) {
                foreach ([$from->year, $to->year] as $year) {
                    $d = $holiday->date->copy()->year((int) $year);
                    $iso = $d->toDateString();
                    if ($iso >= $from->toDateString() && $iso <= $to->toDateString()) {
                        $dates[$iso] = true;
                    }
                }

                continue;
            }
            $iso = $holiday->date->toDateString();
            if ($iso >= $from->toDateString() && $iso <= $to->toDateString()) {
                $dates[$iso] = true;
            }
        }

        return $dates;
    }

    private function roundDays(float $days, string $mode): float
    {
        return match ($mode) {
            'half' => round($days * 2) / 2,
            'whole' => (float) round($days),
            default => round($days, 2),
        };
    }
}
