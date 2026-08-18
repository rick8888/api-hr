<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Leave\CompensatoryOffData;
use App\Domain\Employee\Models\Employee;
use App\Domain\Leave\Models\CompensatoryOff;
use App\Domain\Leave\Repositories\CompensatoryOffRepositoryInterface;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompensatoryOffService
{
    public function __construct(
        private readonly CompensatoryOffRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CompensatoryOff>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $companyId = $this->resolveCompanyId($actor, write: false);
        $scopedFilters = $this->applySelfServiceScope($actor, $filters);

        if ($scopedFilters === null) {
            return new Collection();
        }

        unset($scopedFilters['mine']);

        $rows = $this->repository->listByCompany($companyId, $scopedFilters);

        foreach ($rows as $row) {
            $this->refreshExpiredUsage($row);
        }

        return $rows;
    }

    public function createForActor(User $actor, CompensatoryOffData $data): CompensatoryOff
    {
        $companyId = $this->resolveCompanyId($actor, write: true);

        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = CompensatoryOffData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);
        $this->assertValidDates($data->workedDate, $data->expiryDate);

        return DB::transaction(function () use ($companyId, $data) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['status'] = 'pending';
            $payload['usage_status'] = null;
            $payload['manager_name'] = null;
            $payload['reject_reason'] = null;
            $payload['reviewed_at'] = null;

            $row = $this->repository->create($payload);

            $this->audit->log('compensatory_off.created', $row, [
                'employee_id' => $row->employee_id,
                'worked_date' => optional($row->worked_date)->toDateString(),
            ]);

            return $row;
        });
    }

    public function updateStatusForActor(
        User $actor,
        CompensatoryOff $compensatoryOff,
        string $status,
        ?string $rejectReason = null,
    ): CompensatoryOff {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($compensatoryOff, $companyId);
        $this->ensureSelfServiceOwnsRequest($actor, $compensatoryOff);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider ou rejeter cette demande.');
        }

        if (! EntityStatusCatalog::contains('compensatory_offs', $status, CompensatoryOff::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => 'Statut invalide.',
            ]);
        }

        if ($compensatoryOff->status !== 'pending' && $status !== $compensatoryOff->status) {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut changer de statut.',
            ]);
        }

        return DB::transaction(function () use ($actor, $compensatoryOff, $status, $rejectReason) {
            $payload = [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'reject_reason' => $status === 'rejected' ? $rejectReason : null,
                'usage_status' => $status === 'approved' ? 'available' : null,
            ];

            $updated = $this->repository->update($compensatoryOff, $payload);
            $this->refreshExpiredUsage($updated);

            $this->audit->log('compensatory_off.status_updated', $updated, ['status' => $status]);

            return $updated->refresh()->load('employee');
        });
    }

    public function deleteForActor(User $actor, CompensatoryOff $compensatoryOff): bool
    {
        $companyId = $this->resolveCompanyId($actor, write: true);
        $this->ensureBelongsToCompany($compensatoryOff, $companyId);
        $this->ensureSelfServiceOwnsRequest($actor, $compensatoryOff);

        if ($this->isSelfServiceActor($actor) && $compensatoryOff->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être annulée.',
            ]);
        }

        return DB::transaction(function () use ($compensatoryOff) {
            $this->audit->log('compensatory_off.deleted', $compensatoryOff, [
                'employee_id' => $compensatoryOff->employee_id,
            ]);

            return $this->repository->delete($compensatoryOff);
        });
    }

    public function findForActor(User $actor, CompensatoryOff $compensatoryOff): CompensatoryOff
    {
        $this->ensureBelongsToCompany($compensatoryOff, $this->resolveCompanyId($actor, write: false));
        $this->ensureSelfServiceOwnsRequest($actor, $compensatoryOff);
        $row = $compensatoryOff->loadMissing('employee');
        $this->refreshExpiredUsage($row);

        return $row;
    }

    private function refreshExpiredUsage(CompensatoryOff $row): void
    {
        if ($row->status !== 'approved') {
            return;
        }

        if ($row->usage_status === 'used') {
            return;
        }

        $expiry = optional($row->expiry_date)->toDateString();
        if ($expiry && $expiry < now()->toDateString() && $row->usage_status !== 'expired') {
            $row->usage_status = 'expired';
            $row->save();
        }
    }

    private function resolveCompanyId(User $actor, bool $write = true): int
    {
        $roles = $write
            ? ['tenant', 'admin', 'team_manager', 'employe']
            : ['tenant', 'admin', 'team_manager', 'employe'];

        if ($actor->company_id === null || ! in_array($actor->role, $roles, true)) {
            throw new AuthorizationException('Accès non autorisé aux compensatory offs.');
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

    private function ensureSelfServiceOwnsRequest(User $actor, CompensatoryOff $row): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $row->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Compensatory off introuvable.');
        }
    }

    private function ensureBelongsToCompany(CompensatoryOff $row, int $companyId): void
    {
        if ($row->company_id !== $companyId) {
            throw new AuthorizationException('Compensatory off introuvable.');
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

    private function assertValidDates(string $workedDate, string $expiryDate): void
    {
        if ($expiryDate < $workedDate) {
            throw ValidationException::withMessages([
                'expiry_date' => "La date d'expiration doit être postérieure ou égale à la date travaillée.",
            ]);
        }
    }
}
