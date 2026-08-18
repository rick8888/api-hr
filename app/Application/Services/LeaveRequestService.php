<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Leave\LeaveRequestData;
use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Organization\Models\Designation;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveRequestService
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
        private readonly LeaveTypeRepositoryInterface $leaveTypes,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function filterOptionsForActor(User $actor): array
    {
        $companyId = $this->resolveCompanyId($actor);

        $catalogDesignations = Designation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->pluck('name');

        $employeeJobTitles = Employee::query()
            ->where('company_id', $companyId)
            ->whereNotNull('job_title')
            ->where('job_title', '!=', '')
            ->distinct()
            ->pluck('job_title');

        $designations = $catalogDesignations
            ->merge($employeeJobTitles)
            ->unique()
            ->sort()
            ->values();

        $leaveTypes = $this->leaveTypes->listByCompany($companyId)
            ->where('is_active', true)
            ->map(fn ($type) => ['code' => $type->code, 'name' => $type->name])
            ->values();

        return [
            'designations' => $designations,
            'leave_types' => $leaveTypes,
            'statuses' => EntityStatusCatalog::codes('leave_requests', LeaveRequest::STATUSES),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, LeaveRequest>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor);
        $scopedFilters = $this->applySelfServiceScope($actor, $filters);

        if ($scopedFilters === null) {
            return new Collection();
        }

        unset($scopedFilters['mine']);

        return $this->repository->listByCompany($companyId, $scopedFilters);
    }

    public function createForActor(User $actor, LeaveRequestData $data): LeaveRequest
    {
        $companyId = $this->resolveCompanyId($actor);

        // Self-service (employé) ou admin sans employee_id : rattacher à sa fiche Employee.
        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = LeaveRequestData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);
        $this->assertValidDates($data->startDate, $data->endDate, $data->durationKind);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['status'] = 'pending';
            $payload['manager_name'] = null;
            $payload['cancel_reason'] = null;
            $payload['reviewed_at'] = null;

            $leaveRequest = $this->repository->create($payload);

            $this->audit->log('leave_request.created', $leaveRequest, [
                'employee_id' => $leaveRequest->employee_id,
                'leave_type' => $leaveRequest->leave_type,
            ]);

            return $leaveRequest;
        });
    }

    /** @param array<string, mixed> $validated */
    public function updateForActor(User $actor, LeaveRequest $leaveRequest, array $validated): LeaveRequest
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($leaveRequest, $companyId);
        $this->ensureSelfServiceOwnsRequest($actor, $leaveRequest);

        if ($leaveRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être modifiée.',
            ]);
        }

        return DB::transaction(function () use ($actor, $leaveRequest, $validated, $companyId) {
            $payload = array_merge($this->modelToArray($leaveRequest), $validated);

            if ($this->isSelfServiceActor($actor)) {
                $payload['employee_id'] = $leaveRequest->employee_id;
            } elseif (isset($payload['employee_id'])) {
                $this->ensureEmployeeBelongsToCompany((int) $payload['employee_id'], $companyId);
            }

            $data = LeaveRequestData::fromArray($payload);
            $this->assertValidDates($data->startDate, $data->endDate, $data->durationKind);

            $updated = $this->repository->update($leaveRequest, $data->toModelArray());

            $this->audit->log('leave_request.updated', $updated, [
                'employee_id' => $updated->employee_id,
            ]);

            return $updated;
        });
    }

    public function updateStatusForActor(
        User $actor,
        LeaveRequest $leaveRequest,
        string $status,
        ?string $cancelReason = null,
    ): LeaveRequest {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($leaveRequest, $companyId);
        $this->ensureSelfServiceOwnsRequest($actor, $leaveRequest);

        if (! EntityStatusCatalog::contains('leave_requests', $status, LeaveRequest::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => 'Statut invalide.',
            ]);
        }

        if ($this->isSelfServiceActor($actor) && $status !== 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Vous pouvez uniquement annuler vos propres demandes.',
            ]);
        }

        if ($leaveRequest->status !== 'pending' && $status !== $leaveRequest->status) {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut changer de statut.',
            ]);
        }

        return DB::transaction(function () use ($actor, $leaveRequest, $status, $cancelReason) {
            $payload = [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'cancel_reason' => $status === 'cancelled' ? $cancelReason : null,
            ];

            $updated = $this->repository->update($leaveRequest, $payload);

            $this->audit->log('leave_request.status_updated', $updated, ['status' => $status]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, LeaveRequest $leaveRequest): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($leaveRequest, $companyId);
        $this->ensureSelfServiceOwnsRequest($actor, $leaveRequest);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Suppression non autorisée. Annulez la demande à la place.');
        }

        return DB::transaction(function () use ($leaveRequest) {
            $this->audit->log('leave_request.deleted', $leaveRequest, [
                'employee_id' => $leaveRequest->employee_id,
            ]);

            return $this->repository->delete($leaveRequest);
        });
    }

    public function findForActor(User $actor, LeaveRequest $leaveRequest): LeaveRequest
    {
        $this->ensureBelongsToCompany($leaveRequest, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwnsRequest($actor, $leaveRequest);

        return $leaveRequest->loadMissing('employee');
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux demandes de congé.');
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
     * Scope “mes demandes” pour le rôle employé, ou si mine=1.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null  null = aucun profil employé → liste vide
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

    private function ensureSelfServiceOwnsRequest(User $actor, LeaveRequest $leaveRequest): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $leaveRequest->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Demande de congé introuvable.');
        }
    }

    private function ensureBelongsToCompany(LeaveRequest $leaveRequest, int $companyId): void
    {
        if ($leaveRequest->company_id !== $companyId) {
            throw new AuthorizationException('Demande de congé introuvable.');
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

    private function assertValidDates(string $startDate, string $endDate, string $durationKind): void
    {
        if ($endDate < $startDate) {
            throw ValidationException::withMessages([
                'end_date' => 'La date de fin doit être postérieure ou égale à la date de début.',
            ]);
        }

        if ($durationKind === 'half' && $startDate !== $endDate) {
            throw ValidationException::withMessages([
                'end_date' => 'Une demi-journée doit porter sur une seule date.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function modelToArray(LeaveRequest $leaveRequest): array
    {
        return [
            'employee_id' => $leaveRequest->employee_id,
            'leave_type' => $leaveRequest->leave_type,
            'duration_kind' => $leaveRequest->duration_kind,
            'start_date' => optional($leaveRequest->start_date)->toDateString(),
            'end_date' => optional($leaveRequest->end_date)->toDateString(),
            'total_days' => $leaveRequest->total_days,
            'reason' => $leaveRequest->reason,
            'emergency_contact' => $leaveRequest->emergency_contact,
            'traveling_abroad' => $leaveRequest->traveling_abroad,
            'document' => $leaveRequest->document,
            'status' => $leaveRequest->status,
            'manager_name' => $leaveRequest->manager_name,
            'cancel_reason' => $leaveRequest->cancel_reason,
            'reviewed_at' => optional($leaveRequest->reviewed_at)?->toDateTimeString(),
        ];
    }
}
