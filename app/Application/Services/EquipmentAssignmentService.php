<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Domain\Equipment\Repositories\EquipmentAssignmentRepositoryInterface;
use App\Domain\Equipment\Repositories\EquipmentRepositoryInterface;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EquipmentAssignmentService
{
    public function __construct(
        private readonly EquipmentAssignmentRepositoryInterface $repository,
        private readonly EquipmentRepositoryInterface $equipments,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, EquipmentAssignment>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor);
        $scoped = $this->applySelfServiceScope($actor, $filters);
        if ($scoped === null) {
            return new Collection();
        }
        unset($scoped['mine']);

        if ($this->isSelfServiceActor($actor) && empty($scoped['status'])) {
            $scoped['status'] = 'assigned';
        }

        return $this->repository->listByCompany($companyId, $scoped);
    }

    public function reportIssueForActor(
        User $actor,
        EquipmentAssignment $assignment,
        string $note,
    ): EquipmentAssignment {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($assignment, $companyId);
        $this->ensureSelfServiceOwns($actor, $assignment);

        if ($assignment->status !== 'assigned') {
            throw ValidationException::withMessages([
                'status' => 'Seul un équipement attribué peut faire l\'objet d\'un signalement.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $note) {
            $updated = $this->repository->update($assignment, [
                'issue_note' => $note,
                'issue_reported_at' => now()->toDateTimeString(),
            ]);

            $this->audit->log('equipment_assignment.issue_reported', $updated, [
                'equipment_id' => $updated->equipment_id,
            ]);

            return $updated;
        });
    }

    public function returnForActor(User $actor, EquipmentAssignment $assignment): EquipmentAssignment
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($assignment, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas retourner cet équipement.');
        }

        if ($assignment->status !== 'assigned') {
            throw ValidationException::withMessages([
                'status' => 'Cet équipement est déjà retourné.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $companyId) {
            $updated = $this->repository->update($assignment, [
                'status' => 'returned',
            ]);

            $equipment = $this->equipments->findForCompany(
                (int) $assignment->equipment_id,
                $companyId,
            );
            if ($equipment !== null) {
                $this->equipments->update($equipment, [
                    'stock' => $equipment->stock + 1,
                ]);
            }

            $this->audit->log('equipment_assignment.returned', $updated, [
                'equipment_id' => $updated->equipment_id,
            ]);

            return $updated;
        });
    }

    public function findForActor(User $actor, EquipmentAssignment $assignment): EquipmentAssignment
    {
        $this->ensureBelongsToCompany($assignment, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $assignment);

        return $assignment->loadMissing(['equipment', 'employee']);
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux attributions d\'équipement.');
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

    private function ensureSelfServiceOwns(User $actor, EquipmentAssignment $assignment): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $assignment->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Attribution introuvable.');
        }
    }

    private function ensureBelongsToCompany(EquipmentAssignment $assignment, int $companyId): void
    {
        if ($assignment->company_id !== $companyId) {
            throw new AuthorizationException('Attribution introuvable.');
        }
    }
}
