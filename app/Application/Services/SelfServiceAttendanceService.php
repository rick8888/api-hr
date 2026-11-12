<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Attendance\Models\AttendanceBreak;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Attendance\Repositories\AttendanceRecordRepositoryInterface;
use App\Domain\Employee\Models\Employee;
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
        private readonly AttendanceMetricsCalculator $metrics,
    ) {}

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
        if ($todayRecord === null) {
            $yesterday = $today->copy()->subDay();
            $todayRecord = $records->first(function (AttendanceRecord $record) use ($today, $yesterday) {
                return $record->attendance_date !== null
                    && $record->attendance_date->betweenIncluded($yesterday, $today)
                    && filled($record->check_in)
                    && blank($record->check_out);
            });
        }

        return [
            'server_now' => Carbon::now()->toIso8601String(),
            'summary' => [
                'present_days' => $monthRecords->sum(
                    fn (AttendanceRecord $r) => in_array($r->status, ['on_time', 'late'], true)
                        ? 1.0
                        : ($r->status === 'half_day' ? 0.5 : 0.0)
                ),
                'absent_days' => $monthRecords->sum(
                    fn (AttendanceRecord $r) => $r->status === 'absent'
                        ? 1.0
                        : ($r->status === 'half_day' ? 0.5 : 0.0)
                ),
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
    public function startBreakForActor(User $actor): array
    {
        $employee = $this->resolveEmployee($actor);
        $record = $this->openRecord((int) $actor->company_id, (int) $employee->id);
        if ($record === null) {
            throw ValidationException::withMessages([
                'break' => 'Vous devez pointer votre arrivée avant de commencer une pause.',
            ]);
        }

        DB::transaction(function () use ($record) {
            $lockedRecord = AttendanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (filled($lockedRecord->check_out)) {
                throw ValidationException::withMessages([
                    'break' => 'La présence est déjà terminée.',
                ]);
            }
            if ($lockedRecord->breaks()->whereNull('ended_at')->lockForUpdate()->first() !== null) {
                throw ValidationException::withMessages([
                    'break' => 'Une pause est déjà en cours.',
                ]);
            }

            $break = $lockedRecord->breaks()->create([
                'started_at' => Carbon::now(),
            ]);
            $this->audit->log('attendance_break.started', $break, [
                'attendance_record_id' => $lockedRecord->id,
                'employee_id' => $lockedRecord->employee_id,
            ]);
        });

        return $this->showForActor($actor);
    }

    /** @return array<string, mixed> */
    public function endBreakForActor(User $actor): array
    {
        $employee = $this->resolveEmployee($actor);
        $record = $this->openRecord((int) $actor->company_id, (int) $employee->id);
        if ($record === null) {
            throw ValidationException::withMessages([
                'break' => 'Aucune pause en cours pour cette présence.',
            ]);
        }

        DB::transaction(function () use ($record) {
            $lockedRecord = AttendanceRecord::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (filled($lockedRecord->check_out)) {
                throw ValidationException::withMessages([
                    'break' => 'La présence est déjà terminée.',
                ]);
            }
            /** @var AttendanceBreak|null $break */
            $break = $lockedRecord->breaks()
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->orderByDesc('started_at')
                ->first();

            if ($break === null) {
                throw ValidationException::withMessages([
                    'break' => 'Aucune pause en cours pour cette présence.',
                ]);
            }

            $duration = $break->finish(Carbon::now());
            $this->audit->log('attendance_break.ended', $break, [
                'attendance_record_id' => $lockedRecord->id,
                'employee_id' => $lockedRecord->employee_id,
                'duration_minutes' => $duration,
            ]);
        });

        return $this->showForActor($actor);
    }

    /** @return array<string, mixed> */
    public function checkInForActor(User $actor): array
    {
        $employee = $this->resolveEmployee($actor);
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i');

        $existing = $this->todayRecord((int) $actor->company_id, (int) $employee->id, $today);
        $openRecord = $this->openRecord((int) $actor->company_id, (int) $employee->id);
        if ($openRecord !== null && ($existing === null || $openRecord->isNot($existing))) {
            throw ValidationException::withMessages([
                'check_in' => 'Terminez le pointage précédent avant de commencer une nouvelle journée.',
            ]);
        }

        if ($existing !== null && filled($existing->check_in)) {
            throw ValidationException::withMessages([
                'check_in' => 'Vous êtes déjà pointé pour aujourd’hui.',
            ]);
        }

        $shiftId = $existing?->shift_id ?? $this->assignedShiftId($employee, (int) $actor->company_id);

        DB::transaction(function () use ($actor, $employee, $today, $now, $existing, $shiftId) {
            $payload = $this->withMetrics([
                'company_id' => (int) $actor->company_id,
                'employee_id' => (int) $employee->id,
                'attendance_date' => $today,
                'shift_id' => $shiftId,
                'site_id' => $existing?->site_id,
                'check_in' => $now,
                'check_out' => null,
                'status' => in_array($existing?->status, ['absent', 'leave'], true)
                    ? 'on_time'
                    : ($existing?->status ?: 'on_time'),
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
        $checkedOutAt = Carbon::now();
        $now = $checkedOutAt->format('H:i');

        $todayRecord = $this->todayRecord(
            (int) $actor->company_id,
            (int) $employee->id,
            Carbon::today()->toDateString(),
        );
        if ($todayRecord !== null && filled($todayRecord->check_out)) {
            throw ValidationException::withMessages([
                'check_out' => 'Vous êtes déjà pointé en sortie pour aujourd’hui.',
            ]);
        }

        $existing = $this->openRecord((int) $actor->company_id, (int) $employee->id);

        if ($existing === null || blank($existing->check_in)) {
            throw ValidationException::withMessages([
                'check_out' => 'Vous devez d’abord pointer l’entrée.',
            ]);
        }

        DB::transaction(function () use ($existing, $now, $checkedOutAt) {
            $lockedRecord = AttendanceRecord::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
            if (filled($lockedRecord->check_out)) {
                throw ValidationException::withMessages([
                    'check_out' => 'Vous êtes déjà pointé en sortie pour aujourd’hui.',
                ]);
            }
            $this->closeActiveBreak($lockedRecord, $checkedOutAt);
            $payload = $this->withMetrics([
                'company_id' => (int) $lockedRecord->company_id,
                'employee_id' => (int) $lockedRecord->employee_id,
                'attendance_date' => $lockedRecord->attendance_date?->toDateString(),
                'shift_id' => $lockedRecord->shift_id,
                'site_id' => $lockedRecord->site_id,
                'check_in' => $lockedRecord->check_in ? substr((string) $lockedRecord->check_in, 0, 5) : null,
                'check_out' => $now,
                'status' => $lockedRecord->status ?: 'on_time',
            ]);

            $record = $this->repository->update($lockedRecord, $payload);
            $this->audit->log('attendance_record.check_out', $record, [
                'employee_id' => $record->employee_id,
            ]);
        });

        return $this->showForActor($actor);
    }

    private function resolveEmployee(User $actor): Employee
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

        if (! $employee->is_active || $employee->terminated_at !== null || $employee->isSuspended() || $employee->isRetired()) {
            throw new AuthorizationException('Votre compte employé est inactif.');
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

    private function openRecord(int $companyId, int $employeeId): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', '>=', Carbon::yesterday()->toDateString())
            ->whereDate('attendance_date', '<=', Carbon::today()->toDateString())
            ->whereNotNull('check_in')
            ->whereNull('check_out')
            ->orderByDesc('attendance_date')
            ->first();
    }

    private function closeActiveBreak(AttendanceRecord $record, Carbon $endedAt): void
    {
        /** @var AttendanceBreak|null $break */
        $break = $record->breaks()
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->orderByDesc('started_at')
            ->first();
        if ($break === null) {
            return;
        }

        $duration = $break->finish($endedAt);
        $this->audit->log('attendance_break.ended_at_check_out', $break, [
            'attendance_record_id' => $record->id,
            'employee_id' => $record->employee_id,
            'duration_minutes' => $duration,
        ]);
    }

    private function assignedShiftId(Employee $employee, int $companyId): ?int
    {
        $metadata = is_array($employee->metadata) ? $employee->metadata : [];
        $shiftId = $metadata['shift_id'] ?? null;
        if ($shiftId === null || $shiftId === '') {
            return null;
        }

        $shift = Shift::query()
            ->whereKey((int) $shiftId)
            ->where('company_id', $companyId)
            ->first();
        if ($shift === null) {
            throw ValidationException::withMessages([
                'shift' => 'Le shift affecté à votre profil est introuvable pour cette société.',
            ]);
        }

        return (int) $shift->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withMetrics(array $payload): array
    {
        $shift = isset($payload['shift_id']) ? Shift::query()->find($payload['shift_id']) : null;

        return $this->metrics->calculate($payload, $shift);
    }
}
