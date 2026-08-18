<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Attendance\Repositories\AttendanceRecordRepositoryInterface;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agrégat self-service pour GET /attendance (KPIs + aujourd'hui + historique)
 * + pointages check-in / check-out.
 */
final class SelfServiceAttendanceService
{
    public function __construct(
        private readonly AttendanceRecordService $attendanceRecords,
        private readonly AttendanceRecordRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function showForActor(User $actor): array
    {
        $records = $this->attendanceRecords->listForActor($actor, ['mine' => true]);
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $monthRecords = $records->filter(function (AttendanceRecord $record) use ($monthStart, $monthEnd) {
            $date = $record->attendance_date;

            return $date !== null && $date->betweenIncluded($monthStart, $monthEnd);
        });

        $todayRecord = $records->first(
            fn (AttendanceRecord $record) => $record->attendance_date?->isSameDay($today)
        );

        return [
            'summary' => [
                'present_days' => $monthRecords->filter(
                    fn (AttendanceRecord $r) => in_array($r->status, ['on_time', 'late', 'half_day'], true)
                )->count(),
                'absent_days' => $monthRecords->where('status', 'absent')->count(),
                'late_arrivals' => $monthRecords->where('status', 'late')->count(),
                'half_days' => $monthRecords->where('status', 'half_day')->count(),
                'month_label' => 'This Month',
            ],
            'today' => [
                'date' => $today->toDateString(),
                'date_label' => $today->format('l, F d, Y'),
                'has_record' => $todayRecord !== null,
                'message' => 'No attendance record for today. Please check in to mark your attendance.',
                'record' => $todayRecord
                    ? (new AttendanceRecordResource($todayRecord))->resolve()
                    : null,
            ],
            'history' => AttendanceRecordResource::collection($records)->resolve(),
        ];
    }

    /** @return array<string, mixed> */
    public function checkInForActor(User $actor): array
    {
        $employee = $this->resolveEmployee($actor);
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i');

        $existing = $this->todayRecord((int) $actor->company_id, (int) $employee->id, $today);

        if ($existing !== null && filled($existing->check_in)) {
            throw ValidationException::withMessages([
                'check_in' => 'Vous êtes déjà pointé pour aujourd’hui.',
            ]);
        }

        DB::transaction(function () use ($actor, $employee, $today, $now, $existing) {
            $payload = $this->withMetrics([
                'company_id' => (int) $actor->company_id,
                'employee_id' => (int) $employee->id,
                'attendance_date' => $today,
                'shift_id' => $existing?->shift_id,
                'site_id' => $existing?->site_id,
                'check_in' => $now,
                'check_out' => null,
                'status' => $existing?->status ?: 'on_time',
            ]);

            if ($existing !== null) {
                $record = $this->repository->update($existing, $payload);
                $this->audit->log('attendance_record.check_in', $record, [
                    'employee_id' => $record->employee_id,
                ]);

                return;
            }

            $record = $this->repository->create($payload);
            $this->audit->log('attendance_record.check_in', $record, [
                'employee_id' => $record->employee_id,
            ]);
        });

        return $this->showForActor($actor);
    }

    /** @return array<string, mixed> */
    public function checkOutForActor(User $actor): array
    {
        $employee = $this->resolveEmployee($actor);
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i');

        $existing = $this->todayRecord((int) $actor->company_id, (int) $employee->id, $today);

        if ($existing === null || blank($existing->check_in)) {
            throw ValidationException::withMessages([
                'check_out' => 'Vous devez d’abord pointer l’entrée.',
            ]);
        }

        if (filled($existing->check_out)) {
            throw ValidationException::withMessages([
                'check_out' => 'Vous êtes déjà pointé en sortie pour aujourd’hui.',
            ]);
        }

        DB::transaction(function () use ($existing, $now) {
            $payload = $this->withMetrics([
                'company_id' => (int) $existing->company_id,
                'employee_id' => (int) $existing->employee_id,
                'attendance_date' => $existing->attendance_date?->toDateString(),
                'shift_id' => $existing->shift_id,
                'site_id' => $existing->site_id,
                'check_in' => $existing->check_in ? substr((string) $existing->check_in, 0, 5) : null,
                'check_out' => $now,
                'status' => $existing->status ?: 'on_time',
            ]);

            $record = $this->repository->update($existing, $payload);
            $this->audit->log('attendance_record.check_out', $record, [
                'employee_id' => $record->employee_id,
            ]);
        });

        return $this->showForActor($actor);
    }

    private function resolveEmployee(User $actor): \App\Domain\Employee\Models\Employee
    {
        if ($actor->company_id === null) {
            throw new AuthorizationException('Accès non autorisé aux présences.');
        }

        $employee = $this->employeeProfiles->findOrCreateForUser($actor)
            ?? $this->employeeProfiles->findForUser($actor);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => 'Aucun profil employé lié à ce compte.',
            ]);
        }

        return $employee;
    }

    private function todayRecord(int $companyId, int $employeeId, string $today): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $today)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withMetrics(array $payload): array
    {
        $shift = isset($payload['shift_id']) ? Shift::query()->find($payload['shift_id']) : null;
        $checkIn = $this->minutes($payload['check_in'] ?? null);
        $checkOut = $this->minutes($payload['check_out'] ?? null);
        $shiftStart = $this->minutes($shift?->start_time);
        $shiftEnd = $this->minutes($shift?->end_time);

        $payload['late_minutes'] = $checkIn !== null && $shiftStart !== null
            ? max(0, $checkIn - $shiftStart) : 0;
        $payload['early_out_minutes'] = $checkOut !== null && $shiftEnd !== null
            ? max(0, $shiftEnd - $checkOut) : 0;
        $payload['overtime_minutes'] = $checkOut !== null && $shiftEnd !== null
            ? max(0, $checkOut - $shiftEnd) : 0;

        if (($payload['status'] ?? 'on_time') === 'on_time' && ($payload['late_minutes'] ?? 0) > 0) {
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
