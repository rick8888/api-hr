<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Attendance\AttendanceRecordData;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Attendance\Models\Shift;
use App\Domain\Attendance\Repositories\AttendanceRecordRepositoryInterface;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Site;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceRecordService
{
    public function __construct(
        private readonly AttendanceRecordRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
        private readonly TeamScopeService $teamScope,
        private readonly AttendanceMetricsCalculator $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, AttendanceRecord>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->companyId($actor);
        $scoped = $this->applySelfServiceScope($actor, $filters);

        if ($scoped === null) {
            return new Collection;
        }

        unset($scoped['mine']);

        $scoped = $this->teamScope->mergeEmployeeListFilters($actor, $scoped);
        if ($scoped === null) {
            return new Collection;
        }

        return $this->repository->listByCompany($companyId, $scoped);
    }

    public function createForActor(User $actor, AttendanceRecordData $data): AttendanceRecord
    {
        $this->ensureStaffCanMutate($actor);
        $companyId = $this->companyId($actor);
        $this->validateRelations($companyId, $data);
        $this->teamScope->ensureManagesEmployeeId($actor, $data->employeeId, $companyId);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $this->withMetrics($data->toModelArray());
            $payload['company_id'] = $companyId;
            $record = $this->repository->create($payload);
            $this->audit->log('attendance_record.created', $record, [
                'employee_id' => $record->employee_id,
            ]);

            return $record;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, AttendanceRecord $record, array $validated): AttendanceRecord
    {
        $this->ensureStaffCanMutate($actor);
        $companyId = $this->companyId($actor);
        $this->ensureCompany($record, $companyId);
        $data = AttendanceRecordData::fromArray(array_merge($this->modelArray($record), $validated));
        $this->validateRelations($companyId, $data);
        $this->teamScope->ensureManagesEmployeeId($actor, $data->employeeId, $companyId);

        return DB::transaction(function () use ($record, $data) {
            $updated = $this->repository->update($record, $this->withMetrics($data->toModelArray()));
            $this->audit->log('attendance_record.updated', $updated, [
                'employee_id' => $updated->employee_id,
            ]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, AttendanceRecord $record): bool
    {
        $this->ensureStaffCanMutate($actor);
        $companyId = $this->companyId($actor);
        $this->ensureCompany($record, $companyId);
        $this->teamScope->ensureManagesEmployeeId($actor, (int) $record->employee_id, $companyId);

        return DB::transaction(function () use ($record) {
            $this->audit->log('attendance_record.deleted', $record, [
                'employee_id' => $record->employee_id,
            ]);

            return $this->repository->delete($record);
        });
    }

    private function companyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux présences.');
        }

        return (int) $actor->company_id;
    }

    private function ensureStaffCanMutate(User $actor): void
    {
        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Modification des présences non autorisée.');
        }
    }

    private function isSelfServiceActor(User $actor): bool
    {
        return $actor->role === 'employe';
    }

    private function resolveEmployeeForActor(User $actor): ?Employee
    {
        return $this->employeeProfiles->findOrCreateForUser($actor)
            ?? $this->employeeProfiles->findForUser($actor);
    }

    /**
     * Scope “mes présences” pour le rôle employé, ou si mine=1.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function applySelfServiceScope(User $actor, array $filters): ?array
    {
        $mine = filter_var($filters['mine'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $this->isSelfServiceActor($actor) && ! $mine) {
            return $filters;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null) {
            return null;
        }

        $filters['employee_id'] = $employee->id;

        return $filters;
    }

    private function ensureCompany(AttendanceRecord $record, int $companyId): void
    {
        if ((int) $record->company_id !== $companyId) {
            throw new AuthorizationException('Présence introuvable.');
        }
    }

    private function validateRelations(int $companyId, AttendanceRecordData $data): void
    {
        $validEmployee = Employee::query()
            ->whereKey($data->employeeId)
            ->where('company_id', $companyId)
            ->exists();
        $validShift = $data->shiftId === null || Shift::query()
            ->whereKey($data->shiftId)
            ->where('company_id', $companyId)
            ->exists();
        $validSite = $data->siteId === null || Site::query()
            ->whereKey($data->siteId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $validEmployee || ! $validShift || ! $validSite) {
            throw ValidationException::withMessages(['relations' => 'Employé, shift ou site invalide.']);
        }
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

    /** @return array<string, mixed> */
    private function modelArray(AttendanceRecord $record): array
    {
        return [
            'employee_id' => $record->employee_id,
            'attendance_date' => $record->attendance_date->toDateString(),
            'shift_id' => $record->shift_id,
            'site_id' => $record->site_id,
            'check_in' => $record->check_in ? substr((string) $record->check_in, 0, 5) : null,
            'check_out' => $record->check_out ? substr((string) $record->check_out, 0, 5) : null,
            'status' => $record->status,
        ];
    }
}
