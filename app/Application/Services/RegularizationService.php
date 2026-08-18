<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Attendance\RegularizationData;
use App\Domain\Attendance\Models\Regularization;
use App\Domain\Attendance\Repositories\RegularizationRepositoryInterface;
use App\Domain\Employee\Models\Employee;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegularizationService
{
    public function __construct(
        private readonly RegularizationRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Regularization>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor);
        $scoped = $this->applySelfServiceScope($actor, $filters);

        if ($scoped === null) {
            return new Collection();
        }

        unset($scoped['mine']);

        return $this->repository->listByCompany($companyId, $scoped);
    }

    public function createForActor(User $actor, RegularizationData $data): Regularization
    {
        $companyId = $this->resolveCompanyId($actor);

        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = RegularizationData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['status'] = 'pending';
            $payload['manager_name'] = null;
            $payload['reviewed_at'] = null;

            $regularization = $this->repository->create($payload);

            $this->audit->log('regularization.created', $regularization, [
                'employee_id' => $regularization->employee_id,
                'type' => $regularization->type,
            ]);

            return $regularization;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, Regularization $regularization, array $validated): Regularization
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($regularization, $companyId);
        $this->ensureSelfServiceOwns($actor, $regularization);

        if ($regularization->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être modifiée.',
            ]);
        }

        return DB::transaction(function () use ($actor, $regularization, $validated, $companyId) {
            $payload = array_merge($this->modelToArray($regularization), $validated);

            if ($this->isSelfServiceActor($actor)) {
                $payload['employee_id'] = $regularization->employee_id;
            } elseif (isset($payload['employee_id'])) {
                $this->ensureEmployeeBelongsToCompany((int) $payload['employee_id'], $companyId);
            }

            $updated = $this->repository->update(
                $regularization,
                RegularizationData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('regularization.updated', $updated, [
                'employee_id' => $updated->employee_id,
            ]);

            return $updated;
        });
    }

    public function updateStatusForActor(User $actor, Regularization $regularization, string $status): Regularization
    {
        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider une régularisation.');
        }

        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($regularization, $companyId);

        if (! EntityStatusCatalog::contains('regularizations', $status, Regularization::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => 'Statut invalide.',
            ]);
        }

        return DB::transaction(function () use ($actor, $regularization, $status) {
            $payload = [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
            ];

            $updated = $this->repository->update($regularization, $payload);

            $this->audit->log('regularization.status_updated', $updated, [
                'employee_id' => $updated->employee_id,
                'status' => $status,
            ]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, Regularization $regularization): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($regularization, $companyId);
        $this->ensureSelfServiceOwns($actor, $regularization);

        if ($this->isSelfServiceActor($actor) && $regularization->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être supprimée.',
            ]);
        }

        return DB::transaction(function () use ($regularization) {
            $this->audit->log('regularization.deleted', $regularization, [
                'employee_id' => $regularization->employee_id,
            ]);

            return $this->repository->delete($regularization);
        });
    }

    public function findForActor(User $actor, Regularization $regularization): Regularization
    {
        $this->ensureBelongsToCompany($regularization, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $regularization);

        return $regularization->loadMissing('employee');
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux régularisations.');
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

    /**
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

    private function ensureSelfServiceOwns(User $actor, Regularization $regularization): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $regularization->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Régularisation introuvable.');
        }
    }

    private function ensureBelongsToCompany(Regularization $regularization, int $companyId): void
    {
        if ($regularization->company_id !== $companyId) {
            throw new AuthorizationException('Régularisation introuvable.');
        }
    }

    private function ensureEmployeeBelongsToCompany(int $employeeId, int $companyId): void
    {
        $exists = Employee::query()
            ->where('id', $employeeId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Employé introuvable pour cette société.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function modelToArray(Regularization $regularization): array
    {
        return [
            'employee_id' => $regularization->employee_id,
            'attendance_date' => optional($regularization->attendance_date)->toDateString(),
            'type' => $regularization->type,
            'reason' => $regularization->reason,
            'check_in' => $this->formatTime($regularization->check_in),
            'check_out' => $this->formatTime($regularization->check_out),
            'attachment_count' => $regularization->attachment_count,
            'status' => $regularization->status,
            'manager_name' => $regularization->manager_name,
            'reviewed_at' => optional($regularization->reviewed_at)?->toDateTimeString(),
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
