<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeOffboardingTask;
use App\Domain\Employee\Models\PerformanceObjective;
use App\Domain\Employee\Models\PerformanceReview;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Holiday;
use App\Domain\Recruitment\Models\Candidate;
use App\Domain\Recruitment\Models\JobOpening;
use App\Http\Resources\EmployeeAvatarUrl;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Agrégat tableau de bord admin (GET /dashboard/admin).
 */
final class AdminDashboardService
{
    public function __construct(
        private readonly TeamScopeService $teamScope,
    ) {}

    /** @return array<string, mixed> */
    public function showForActor(User $actor, string $period = 'today'): array
    {
        $companyId = $this->companyId($actor);
        $today = Carbon::today();
        $period = in_array($period, ['today', 'week', 'month'], true) ? $period : 'today';

        $employees = Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->tap(fn ($q) => $this->excludeNonWorkforceProfiles($q, $companyId))
            ->tap(fn ($q) => $this->teamScope->constrainEmployeeQuery($q, $actor))
            ->get(['id', 'first_name', 'last_name', 'job_title', 'metadata']);

        $employeeCount = $employees->count();
        $employeeMap = $employees->keyBy('id');
        $canViewRecruitment = $actor->hasAnyPermission('organization.manage', 'employees.manage');

        $applicantCount = 0;
        $openPositions = 0;
        if ($canViewRecruitment) {
            $applicantCount = Candidate::query()->where('company_id', $companyId)->count();
            $openPositions = JobOpening::query()->where('company_id', $companyId)->where('status', 'open')->count();
        }

        $teamEmployeeIds = $this->teamScope->scopedDirectReportIds($actor);

        $todayRecords = AttendanceRecord::query()
            ->with(['employee', 'site'])
            ->where('company_id', $companyId)
            ->when($teamEmployeeIds !== null, function ($q) use ($teamEmployeeIds) {
                $q->whereIn('employee_id', $teamEmployeeIds !== [] ? $teamEmployeeIds : [-1]);
            })
            ->whereDate('attendance_date', $today)
            ->orderBy('check_in')
            ->get();

        $todayPresent = $todayRecords->filter(
            fn (AttendanceRecord $r) => in_array($r->status, ['on_time', 'late', 'half_day'], true)
        )->count();

        $yesterdayPresent = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->when($teamEmployeeIds !== null, function ($q) use ($teamEmployeeIds) {
                $q->whereIn('employee_id', $teamEmployeeIds !== [] ? $teamEmployeeIds : [-1]);
            })
            ->whereDate('attendance_date', $today->copy()->subDay())
            ->whereIn('status', ['on_time', 'late', 'half_day'])
            ->count();

        $employeesLastMonth = Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->tap(fn ($q) => $this->excludeNonWorkforceProfiles($q, $companyId))
            ->tap(fn ($q) => $this->teamScope->constrainEmployeeQuery($q, $actor))
            ->where('created_at', '<', $today->copy()->startOfMonth())
            ->count();

        $applicantsLastMonth = 0;
        $openPositionsLastMonth = 0;
        if ($canViewRecruitment) {
            $applicantsLastMonth = Candidate::query()
                ->where('company_id', $companyId)
                ->where('created_at', '<', $today->copy()->startOfMonth())
                ->count();
            $openPositionsLastMonth = JobOpening::query()
                ->where('company_id', $companyId)
                ->where('status', 'open')
                ->where('created_at', '<', $today->copy()->startOfMonth())
                ->count();
        }

        $kpis = [
            [
                'id' => 'employees',
                'title' => 'Total Employee',
                'value' => $employeeCount,
                'change' => $this->changePercent($employeeCount, $employeesLastMonth),
                'change_direction' => $this->changeDirection($employeeCount, $employeesLastMonth),
                'update_date' => $today->format('F j, Y'),
            ],
        ];

        if ($canViewRecruitment) {
            $kpis[] = [
                'id' => 'applicants',
                'title' => 'Total Applicant',
                'value' => $applicantCount,
                'change' => $this->changePercent($applicantCount, $applicantsLastMonth),
                'change_direction' => $this->changeDirection($applicantCount, $applicantsLastMonth),
                'update_date' => $today->format('F j, Y'),
            ];
        }

        $kpis[] = [
            'id' => 'attendance',
            'title' => 'Today Attendance',
            'value' => $todayPresent,
            'change' => $this->changePercent($todayPresent, $yesterdayPresent),
            'change_direction' => $this->changeDirection($todayPresent, $yesterdayPresent),
            'update_date' => $today->format('F j, Y'),
        ];

        if ($canViewRecruitment) {
            $kpis[] = [
                'id' => 'open_positions',
                'title' => 'Open Positions',
                'value' => $openPositions,
                'change' => $this->changePercent($openPositions, $openPositionsLastMonth),
                'change_direction' => $this->changeDirection($openPositions, $openPositionsLastMonth),
                'update_date' => $today->format('F j, Y'),
            ];
        }

        return [
            'kpis' => $kpis,
            'attendance_chart' => $this->buildChart(
                $companyId,
                $period,
                $today,
                max(1, $employeeCount),
                $teamEmployeeIds,
            ),
            'attendance_today' => $todayRecords
                ->take(12)
                ->map(fn (AttendanceRecord $record) => $this->mapAttendanceRow($record, $employeeMap))
                ->values()
                ->all(),
            'schedule' => $this->buildSchedule($companyId, $today, $teamEmployeeIds),
            'schedule_dates' => $this->scheduleHighlightDates($companyId, $today, $teamEmployeeIds),
            'hr_actions' => $actor->hasPermission('employees.manage')
                ? $this->buildHrActions($companyId, $employees, $today)
                : null,
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array<string, array{count: int, items: array<int, array<string, mixed>>}>
     */
    private function buildHrActions(int $companyId, Collection $employees, Carbon $today): array
    {
        $probationCandidates = $employees
            ->map(function (Employee $employee): ?array {
                $metadata = is_array($employee->metadata) ? $employee->metadata : [];
                if (! empty($metadata['probation_decision'])) {
                    return null;
                }

                $rawDueDate = $metadata['probation_end_date'] ?? null;
                if (! is_string($rawDueDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $rawDueDate)) {
                    return null;
                }

                [$year, $month, $day] = array_map('intval', explode('-', $rawDueDate));
                if (! checkdate($month, $day, $year)) {
                    return null;
                }

                $dueDate = Carbon::createFromFormat('!Y-m-d', $rawDueDate);
                if (! $dueDate instanceof Carbon || $dueDate->toDateString() !== $rawDueDate) {
                    return null;
                }

                return [
                    'id' => $employee->id,
                    'label' => $employee->full_name,
                    'due_date' => $dueDate->toDateString(),
                    'sort_date' => $dueDate->toDateString(),
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null)
            ->filter(fn (array $item): bool => $item['sort_date'] <= $today->copy()->addDays(30)->toDateString());
        $probationCount = $probationCandidates->count();
        $probationItems = $probationCandidates
            ->sortBy('sort_date')
            ->take(5)
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'label' => $item['label'],
                'due_date' => $item['due_date'],
            ])
            ->values()
            ->all();

        $employeeIds = $employees->modelKeys();
        $objectiveQuery = PerformanceObjective::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())
            ->where('status', '!=', 'completed')
            ->whereHas('review', fn (Builder $query) => $query
                ->where('company_id', $companyId)
                ->where('status', PerformanceReview::STATUS_SUBMITTED)
                ->whereIn('employee_id', $employeeIds))
            ->whereHas('review.employee', fn (Builder $query) => $query->where('is_active', true));
        $objectiveCount = (clone $objectiveQuery)->count();
        $objectiveItems = $objectiveQuery
            ->with('review.employee:id,first_name,last_name')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (PerformanceObjective $objective): array => [
                'id' => $objective->id,
                'label' => $objective->review?->employee?->full_name ?? '',
                'detail' => $objective->title,
                'due_date' => $objective->due_date?->toDateString(),
            ])
            ->values()
            ->all();

        $offboardingQuery = EmployeeOffboardingTask::query()
            ->where('status', EmployeeOffboardingTask::STATUS_PENDING)
            ->whereHas('employee', fn (Builder $query) => $query->where('company_id', $companyId));
        $offboardingCount = (clone $offboardingQuery)->count();
        $offboardingItems = $offboardingQuery
            ->with('employee:id,first_name,last_name')
            ->orderBy('employee_id')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (EmployeeOffboardingTask $task): array => [
                'id' => $task->id,
                'label' => $task->employee?->full_name ?? '',
                'detail' => $task->title,
                'due_date' => null,
            ])
            ->values()
            ->all();

        return [
            'probation' => [
                'count' => $probationCount,
                'items' => $probationItems,
            ],
            'objectives' => [
                'count' => $objectiveCount,
                'items' => $objectiveItems,
            ],
            'offboarding' => [
                'count' => $offboardingCount,
                'items' => $offboardingItems,
            ],
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employeeMap
     * @return array<string, mixed>
     */
    private function mapAttendanceRow(AttendanceRecord $record, Collection $employeeMap): array
    {
        $employee = $record->employee ?? $employeeMap->get($record->employee_id);
        $first = trim((string) ($employee?->first_name ?? ''));
        $last = trim((string) ($employee?->last_name ?? ''));
        $name = trim($first.' '.$last) ?: 'Employee';
        $metadata = is_array($employee?->metadata) ? $employee->metadata : [];
        $designation = $metadata['designation']
            ?? $employee?->job_title
            ?? '—';
        $attendanceType = $metadata['attendance_type'] ?? null;
        $type = $this->mapAttendanceType($attendanceType);

        return [
            'id' => $record->id,
            'name' => $name,
            'initials' => $this->initials($first, $last, $name),
            'avatar_url' => EmployeeAvatarUrl::for($employee, request()),
            'designation' => $designation ?: '—',
            'type' => $type,
            'check_in' => $this->formatTime12($record->check_in),
            'status' => $this->mapStatusLabel($record->status, $record->late_minutes),
            'status_value' => $record->status,
        ];
    }

    /** @return list<array{day: string, present: int, late: int, absent: int}> */
    private function buildChart(
        int $companyId,
        string $period,
        Carbon $today,
        int $employeeCount,
        ?array $teamEmployeeIds,
    ): array {
        if ($period === 'today') {
            return [$this->dayBucket($companyId, $today, 'Today', $employeeCount, $teamEmployeeIds)];
        }

        if ($period === 'week') {
            $start = $today->copy()->startOfWeek(Carbon::MONDAY);
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $bars = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $start->copy()->addDays($i);
                $bars[] = $this->dayBucket($companyId, $day, $labels[$i], $employeeCount, $teamEmployeeIds);
            }

            return $bars;
        }

        // month → 4 semaines
        $monthStart = $today->copy()->startOfMonth();
        $bars = [];
        for ($week = 0; $week < 4; $week++) {
            $from = $monthStart->copy()->addWeeks($week);
            $to = $from->copy()->addDays(6)->min($today->copy()->endOfMonth());
            $bars[] = $this->rangeBucket(
                $companyId,
                $from,
                $to,
                'Week '.($week + 1),
                $employeeCount,
                $teamEmployeeIds,
            );
        }

        return $bars;
    }

    /** @return array{day: string, present: int, late: int, absent: int} */
    private function dayBucket(
        int $companyId,
        Carbon $day,
        string $label,
        int $employeeCount,
        ?array $teamEmployeeIds,
    ): array {
        $records = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->when($teamEmployeeIds !== null, function ($q) use ($teamEmployeeIds) {
                $q->whereIn('employee_id', $teamEmployeeIds !== [] ? $teamEmployeeIds : [-1]);
            })
            ->whereDate('attendance_date', $day)
            ->get(['status']);

        return $this->toPercents($records, $label, $employeeCount);
    }

    /** @return array{day: string, present: int, late: int, absent: int} */
    private function rangeBucket(
        int $companyId,
        Carbon $from,
        Carbon $to,
        string $label,
        int $employeeCount,
        ?array $teamEmployeeIds,
    ): array {
        $records = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->when($teamEmployeeIds !== null, function ($q) use ($teamEmployeeIds) {
                $q->whereIn('employee_id', $teamEmployeeIds !== [] ? $teamEmployeeIds : [-1]);
            })
            ->whereDate('attendance_date', '>=', $from)
            ->whereDate('attendance_date', '<=', $to)
            ->get(['status']);

        $days = max(1, (int) $from->diffInDays($to) + 1);

        return $this->toPercents($records, $label, (int) ($employeeCount * $days));
    }

    /**
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array{day: string, present: int, late: int, absent: int}
     */
    private function toPercents(Collection $records, string $label, int $denominator): array
    {
        $present = $records->filter(
            fn ($r) => in_array($r->status, ['on_time', 'half_day'], true)
        )->count();
        $late = $records->where('status', 'late')->count();
        $absent = $records->filter(
            fn ($r) => in_array($r->status, ['absent', 'leave'], true)
        )->count();

        $base = max(1, $denominator);
        $presentPct = (int) round(($present / $base) * 100);
        $latePct = (int) round(($late / $base) * 100);
        $absentPct = (int) round(($absent / $base) * 100);

        // Cap stacked bar to 100%
        $sum = $presentPct + $latePct + $absentPct;
        if ($sum > 100 && $sum > 0) {
            $presentPct = (int) round(($presentPct / $sum) * 100);
            $latePct = (int) round(($latePct / $sum) * 100);
            $absentPct = max(0, 100 - $presentPct - $latePct);
        }

        return [
            'day' => $label,
            'present' => $presentPct,
            'late' => $latePct,
            'absent' => $absentPct,
        ];
    }

    /** @return list<array{date: string, date_iso: string, events: list<array{time: string, title: string, description: string}>}> */
    private function buildSchedule(int $companyId, Carbon $today, ?array $teamEmployeeIds): array
    {
        $from = $today->copy()->startOfMonth();
        $to = $today->copy()->addDays(14)->endOfDay();

        $holidays = Holiday::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();

        $leaves = LeaveRequest::query()
            ->with('employee')
            ->where('company_id', $companyId)
            ->when($teamEmployeeIds !== null, function ($q) use ($teamEmployeeIds) {
                $q->whereIn('employee_id', $teamEmployeeIds !== [] ? $teamEmployeeIds : [-1]);
            })
            ->where('status', 'pending')
            ->whereDate('start_date', '>=', $today)
            ->whereDate('start_date', '<=', $to)
            ->orderBy('start_date')
            ->limit(20)
            ->get();

        /** @var array<string, list<array{time: string, title: string, description: string}>> $byDate */
        $byDate = [];

        foreach ($holidays as $holiday) {
            $iso = $holiday->date?->toDateString();
            if (! $iso) {
                continue;
            }
            $byDate[$iso][] = [
                'time' => 'All day',
                'title' => $holiday->name,
                'description' => $holiday->half_day ? 'Half-day holiday' : 'Company holiday',
            ];
        }

        foreach ($leaves as $leave) {
            $iso = $leave->start_date?->toDateString();
            if (! $iso) {
                continue;
            }
            $employee = $leave->employee;
            $name = $employee
                ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                : 'Employee';
            $byDate[$iso][] = [
                'time' => 'Leave',
                'title' => $name !== '' ? $name : 'Leave request',
                'description' => 'Pending leave approval',
            ];
        }

        ksort($byDate);

        $groups = [];
        foreach ($byDate as $iso => $events) {
            $groups[] = [
                'date' => Carbon::parse($iso)->format('l, d F Y'),
                'date_iso' => $iso,
                'events' => $events,
            ];
        }

        return array_slice($groups, 0, 5);
    }

    /** @return list<string> */
    private function scheduleHighlightDates(int $companyId, Carbon $today, ?array $teamEmployeeIds): array
    {
        $from = $today->copy()->startOfMonth();
        $to = $today->copy()->endOfMonth()->addMonth();

        $holidayDates = Holiday::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        $leaveDates = LeaveRequest::query()
            ->where('company_id', $companyId)
            ->when($teamEmployeeIds !== null, function ($q) use ($teamEmployeeIds) {
                $q->whereIn('employee_id', $teamEmployeeIds !== [] ? $teamEmployeeIds : [-1]);
            })
            ->where('status', 'pending')
            ->whereDate('start_date', '>=', $from)
            ->whereDate('start_date', '<=', $to)
            ->pluck('start_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString());

        return $holidayDates->merge($leaveDates)->unique()->values()->all();
    }

    /**
     * Aligne le KPI sur la liste employés : exclure les comptes tenant
     * (et fiches liées), qui ne sont pas des employés RH.
     *
     * @param  Builder<Employee>  $query
     */
    private function excludeNonWorkforceProfiles($query, int $companyId): void
    {
        $query
            ->where(function ($q) {
                $q->whereNull('metadata->role')
                    ->orWhere('metadata->role', '<>', 'tenant');
            })
            ->whereNotExists(function ($q) use ($companyId) {
                $q->selectRaw('1')
                    ->from('users')
                    ->where('users.company_id', $companyId)
                    ->where('users.role', 'tenant')
                    ->whereRaw('LOWER(users.email) = LOWER(employees.email)');
            });
    }

    private function companyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['admin', 'team_manager'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé au tableau de bord admin.');
        }

        return (int) $actor->company_id;
    }

    private function changePercent(int $current, int $previous): string
    {
        if ($previous <= 0) {
            return $current > 0 ? '100%' : '0%';
        }

        return abs((int) round((($current - $previous) / $previous) * 100)).'%';
    }

    private function changeDirection(int $current, int $previous): string
    {
        return $current >= $previous ? 'up' : 'down';
    }

    private function mapAttendanceType(?string $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['remote', 'wfh', 'home'], true)) {
            return 'Remote';
        }
        if (in_array($value, ['hybrid'], true)) {
            return 'Hybrid';
        }

        return 'Office';
    }

    private function mapStatusLabel(?string $status, ?int $lateMinutes): string
    {
        $s = strtolower((string) $status);
        if ($s === 'late' || ($lateMinutes ?? 0) > 0 && $s === 'on_time') {
            return 'Late';
        }
        if ($s === 'half_day') {
            return 'Half Day';
        }
        if ($s === 'leave') {
            return 'Leave';
        }
        if ($s === 'absent') {
            return 'Absent';
        }

        return 'On Time';
    }

    private function formatTime12(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $raw = substr((string) $value, 0, 5);
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
            return $raw;
        }
        $hours = (int) $m[1];
        $minutes = $m[2];
        $suffix = $hours >= 12 ? 'PM' : 'AM';
        $hours = $hours % 12 ?: 12;

        return sprintf('%02d:%s %s', $hours, $minutes, $suffix);
    }

    private function initials(string $first, string $last, string $name): string
    {
        $a = mb_substr($first, 0, 1);
        $b = mb_substr($last, 0, 1);
        if ($a !== '' || $b !== '') {
            return mb_strtoupper($a.$b);
        }

        return mb_strtoupper(mb_substr($name, 0, 2));
    }
}
