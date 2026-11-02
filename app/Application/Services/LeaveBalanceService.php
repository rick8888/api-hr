<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveBalance;
use App\Domain\Leave\Models\LeaveType;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveBalanceService
{
    public function __construct(
        private readonly LeaveBalanceRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly TeamScopeService $teamScope,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function listForActor(User $actor, int $year, bool $mine = false): array
    {
        $companyId = $this->resolveCompanyId($actor, write: false);

        $employeeFilters = [];
        if ($this->isSelfServiceActor($actor) || $mine) {
            $self = $this->resolveEmployeeForActor($actor);
            $employeeFilters['employee_ids'] = $self ? [(int) $self->id] : [];
        } else {
            $scoped = $this->teamScope->mergeEmployeeListFilters($actor, []);
            if ($scoped === null) {
                $employeeFilters['employee_ids'] = [];
            } elseif (! empty($scoped['employee_ids']) && is_array($scoped['employee_ids'])) {
                $employeeFilters['employee_ids'] = array_values(array_map('intval', $scoped['employee_ids']));
            }
        }

        $employees = $this->repository->employeesForCompany($companyId, $employeeFilters);
        $leaveTypes = $this->repository->activeLeaveTypesForCompany($companyId);
        $balances = $this->repository->balancesForCompanyAndYear($companyId, $year)
            ->keyBy(fn (LeaveBalance $balance) => $balance->employee_id.'|'.$balance->leave_type_id);
        $used = $this->repository->usedDaysForCompanyAndYear($companyId, $year);

        return [
            'year' => $year,
            'leave_types' => $leaveTypes->map(fn (LeaveType $type) => $this->typePayload($type))->values()->all(),
            'employees' => $employees->map(function (Employee $employee) use ($leaveTypes, $balances, $used) {
                $employeeBalances = [];
                $metadata = is_array($employee->metadata) ? $employee->metadata : [];

                foreach ($leaveTypes as $type) {
                    $balance = $balances->get($employee->id.'|'.$type->id);
                    $usedDays = $used[$employee->id.'|'.strtoupper($type->code)] ?? 0.0;
                    $employeeBalances[$type->code] = $this->balancePayload($balance, $usedDays);
                }

                return [
                    'id' => $employee->id,
                    'employee' => $employee->full_name,
                    'employee_code' => $employee->matricule,
                    'designation' => $metadata['designation'] ?? ($employee->job_title ?: '-'),
                    'team' => $metadata['team'] ?? ($employee->department ?: '-'),
                    'department' => $employee->department ?: '-',
                    'is_active' => (bool) $employee->is_active,
                    'status' => $this->resolveEmployeeStatus($employee),
                    'balances' => $employeeBalances,
                ];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function showForActor(User $actor, string $employeeCode, int $year): array
    {
        $companyId = $this->resolveCompanyId($actor, write: false);
        $this->ensureSelfServiceCanAccessCode($actor, $employeeCode);
        $employee = $this->findEmployee($companyId, $employeeCode);
        $this->teamScope->ensureManages($actor, $employee);
        $leaveTypes = $this->repository->activeLeaveTypesForCompany($companyId);
        $balances = $this->repository->balancesForEmployeeAndYear($companyId, $employee->id, $year)
            ->keyBy('leave_type_id');
        $used = $this->repository->usedDaysForCompanyAndYear($companyId, $year);
        $adjustments = $this->repository->adjustmentsForEmployeeAndYear($companyId, $employee->id, $year);
        $metadata = is_array($employee->metadata) ? $employee->metadata : [];

        return [
            'year' => $year,
            'employee' => [
                'id' => $employee->id,
                'employee' => $employee->full_name,
                'employee_code' => $employee->matricule,
                'designation' => $metadata['designation'] ?? ($employee->job_title ?: '-'),
                'team' => $metadata['team'] ?? ($employee->department ?: '-'),
                'department' => $employee->department ?: '-',
                'email' => $employee->email,
                'is_active' => (bool) $employee->is_active,
                'status' => $this->resolveEmployeeStatus($employee),
            ],
            'balances' => $leaveTypes->map(function (LeaveType $type) use ($employee, $balances, $used) {
                $balance = $balances->get($type->id);
                $usedDays = $used[$employee->id.'|'.strtoupper($type->code)] ?? 0.0;

                return array_merge($this->typePayload($type), $this->balancePayload($balance, $usedDays));
            })->values()->all(),
            'adjustments' => $adjustments->map(fn ($adjustment) => [
                'id' => $adjustment->id,
                'date' => $adjustment->created_at?->toIso8601String(),
                'leave_type' => $adjustment->leaveType?->name,
                'type' => $adjustment->type,
                'days' => (float) $adjustment->days,
                'balance_before' => (float) $adjustment->balance_before,
                'balance_after' => (float) $adjustment->balance_after,
                'reason' => $adjustment->reason,
                'adjusted_by' => $adjustment->adjustedBy?->name ?? 'System',
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function setInitialForActor(
        User $actor,
        string $employeeCode,
        LeaveType $leaveType,
        int $year,
        float $entitled,
        ?string $reason,
    ): array {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $employee = $this->findEmployee($companyId, $employeeCode);
        $this->teamScope->ensureManages($actor, $employee);

        if ($leaveType->company_id !== $companyId || ! $leaveType->is_active) {
            throw ValidationException::withMessages(['leave_type_id' => 'Type de congé invalide.']);
        }

        DB::transaction(function () use ($actor, $companyId, $employee, $leaveType, $year, $entitled, $reason) {
            $current = $this->repository->balancesForEmployeeAndYear($companyId, $employee->id, $year)
                ->firstWhere('leave_type_id', $leaveType->id);
            $before = (float) ($current?->entitled ?? 0);
            $balance = $this->repository->upsert(
                $companyId,
                $employee->id,
                $leaveType->id,
                $year,
                ['entitled' => $entitled],
            );

            $this->repository->createAdjustment([
                'leave_balance_id' => $balance->id,
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'adjusted_by' => $actor->id,
                'year' => $year,
                'type' => 'initial_balance',
                'days' => $entitled - $before,
                'balance_before' => $before,
                'balance_after' => $entitled,
                'reason' => $reason ?: 'Initial balance set',
            ]);

            $this->audit->log('leave_balance.initial_set', $balance, [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
                'before' => $before,
                'after' => $entitled,
            ]);
        });

        return $this->showForActor($actor, $employeeCode, $year);
    }

    private function resolveCompanyId(User $actor, bool $write = true): int
    {
        $roles = $write
            ? ['tenant', 'admin', 'team_manager']
            : ['tenant', 'admin', 'team_manager', 'employe'];

        if ($actor->company_id === null || ! in_array($actor->role, $roles, true)) {
            throw new AuthorizationException('Accès non autorisé aux soldes de congé.');
        }

        return (int) $actor->company_id;
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

    private function ensureSelfServiceCanAccessCode(User $actor, string $employeeCode): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $self = $this->resolveEmployeeForActor($actor);
        if ($self === null || strcasecmp((string) $self->matricule, $employeeCode) !== 0) {
            throw new AuthorizationException('Solde de congé introuvable.');
        }
    }

    private function findEmployee(int $companyId, string $employeeCode): Employee
    {
        $employee = $this->repository->findEmployeeByCode($companyId, $employeeCode);

        if ($employee === null) {
            throw ValidationException::withMessages(['employee' => 'Employé introuvable.']);
        }

        return $employee;
    }

    /** @return array<string, mixed> */
    private function typePayload(LeaveType $type): array
    {
        return [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'description' => $type->description,
        ];
    }

    /** @return array<string, float> */
    private function balancePayload(?LeaveBalance $balance, float $used): array
    {
        $entitled = (float) ($balance?->entitled ?? 0);
        $carriedForward = (float) ($balance?->carried_forward ?? 0);
        $additional = (float) ($balance?->additional ?? 0);

        return [
            'entitled' => $entitled,
            'carried_forward' => $carriedForward,
            'additional' => $additional,
            'used' => $used,
            'available' => $entitled + $carriedForward + $additional - $used,
        ];
    }

    private function resolveEmployeeStatus(Employee $employee): string
    {
        if ($employee->terminated_at !== null) {
            return 'terminated';
        }

        if ($employee->isSuspended()) {
            return 'suspended';
        }

        if ($employee->isRetired()) {
            return 'retired';
        }

        return $employee->is_active ? 'active' : 'inactive';
    }
}
