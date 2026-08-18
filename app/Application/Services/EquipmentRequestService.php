<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Employee\Models\Employee;
use App\Domain\Equipment\Models\EquipmentAssignment;
use App\Domain\Equipment\Models\EquipmentRequest;
use App\Domain\Equipment\Repositories\EquipmentAssignmentRepositoryInterface;
use App\Domain\Equipment\Repositories\EquipmentRepositoryInterface;
use App\Domain\Equipment\Repositories\EquipmentRequestRepositoryInterface;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EquipmentRequestService
{
    public function __construct(
        private readonly EquipmentRequestRepositoryInterface $repository,
        private readonly EquipmentRepositoryInterface $equipments,
        private readonly EquipmentAssignmentRepositoryInterface $assignments,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, EquipmentRequest>
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

    public function createForActor(
        User $actor,
        int $equipmentId,
        int $quantity,
        string $priority,
        ?string $reason = null,
        ?string $expectedDate = null,
    ): EquipmentRequest {
        $companyId = $this->resolveCompanyId($actor);
        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee_id' => 'Aucun profil employé lié à votre compte.',
            ]);
        }

        $equipment = $this->equipments->findForCompany($equipmentId, $companyId);
        if ($equipment === null || ! $equipment->is_active) {
            throw ValidationException::withMessages([
                'equipment_id' => 'Équipement indisponible.',
            ]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'La quantité doit être au moins 1.',
            ]);
        }

        if ($equipment->stock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock insuffisant pour cette demande.',
            ]);
        }

        if (! in_array($priority, EquipmentRequest::PRIORITIES, true)) {
            throw ValidationException::withMessages(['priority' => 'Priorité invalide.']);
        }

        return DB::transaction(function () use ($companyId, $equipment, $employee, $quantity, $priority, $reason, $expectedDate) {
            $row = $this->repository->create([
                'company_id' => $companyId,
                'equipment_id' => $equipment->id,
                'employee_id' => $employee->id,
                'quantity' => $quantity,
                'priority' => $priority,
                'status' => 'pending',
                'reason' => $reason,
                'expected_date' => $expectedDate ?: null,
                'manager_name' => null,
                'reject_reason' => null,
                'reviewed_at' => null,
            ]);

            $this->audit->log('equipment_request.created', $row, [
                'equipment_id' => $equipment->id,
                'quantity' => $quantity,
            ]);

            return $row;
        });
    }

    public function cancelForActor(User $actor, EquipmentRequest $request): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($request, $companyId);
        $this->ensureSelfServiceOwns($actor, $request);

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être annulée.',
            ]);
        }

        return DB::transaction(function () use ($request) {
            $this->repository->update($request, ['status' => 'cancelled']);
            $this->audit->log('equipment_request.cancelled', $request, [
                'equipment_id' => $request->equipment_id,
            ]);

            return true;
        });
    }

    public function updateStatusForActor(
        User $actor,
        EquipmentRequest $request,
        string $status,
        ?string $rejectReason = null,
        ?string $serialNumber = null,
    ): EquipmentRequest {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($request, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider cette demande.');
        }

        if (! EntityStatusCatalog::contains('equipment_requests', $status, EquipmentRequest::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }

        if ($request->status !== 'pending' && $status !== $request->status) {
            throw ValidationException::withMessages([
                'status' => 'Cette demande a déjà été traitée.',
            ]);
        }

        return DB::transaction(function () use ($actor, $request, $status, $rejectReason, $serialNumber, $companyId) {
            if ($status === 'approved') {
                $equipment = $this->equipments->findForCompany(
                    (int) $request->equipment_id,
                    $companyId,
                );
                if ($equipment === null || $equipment->stock < $request->quantity) {
                    throw ValidationException::withMessages([
                        'status' => 'Stock insuffisant pour approuver cette demande.',
                    ]);
                }

                $this->equipments->update($equipment, [
                    'stock' => $equipment->stock - $request->quantity,
                ]);

                for ($i = 0; $i < $request->quantity; $i++) {
                    $this->assignments->create([
                        'company_id' => $companyId,
                        'equipment_id' => $request->equipment_id,
                        'employee_id' => $request->employee_id,
                        'equipment_request_id' => $request->id,
                        'serial_number' => $i === 0 ? $serialNumber : null,
                        'status' => 'assigned',
                        'assigned_date' => now()->toDateString(),
                        'issue_note' => null,
                        'issue_reported_at' => null,
                    ]);
                }
            }

            $updated = $this->repository->update($request, [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'reject_reason' => $status === 'rejected' ? $rejectReason : null,
            ]);

            $this->audit->log('equipment_request.status_updated', $updated, [
                'status' => $status,
            ]);

            return $updated;
        });
    }

    public function findForActor(User $actor, EquipmentRequest $request): EquipmentRequest
    {
        $this->ensureBelongsToCompany($request, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $request);

        return $request->loadMissing(['equipment', 'employee']);
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux demandes d\'équipement.');
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

    private function ensureSelfServiceOwns(User $actor, EquipmentRequest $request): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $request->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Demande introuvable.');
        }
    }

    private function ensureBelongsToCompany(EquipmentRequest $request, int $companyId): void
    {
        if ($request->company_id !== $companyId) {
            throw new AuthorizationException('Demande introuvable.');
        }
    }
}
