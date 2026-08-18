<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Loan\LoanData;
use App\Domain\Employee\Models\Employee;
use App\Domain\Loan\Models\Loan;
use App\Domain\Loan\Repositories\LoanRepositoryInterface;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class LoanService
{
    public function __construct(
        private readonly LoanRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Loan>
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

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function createForActor(User $actor, LoanData $data, array $files = []): Loan
    {
        $companyId = $this->resolveCompanyId($actor);

        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = LoanData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);
        $this->assertValidType($data->type);

        return DB::transaction(function () use ($companyId, $data, $files) {
            $paths = [];
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $paths[] = $file->store("loans/{$companyId}", 'public');
            }

            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['code'] = $this->repository->nextCode($companyId);
            $payload['status'] = 'pending';
            $payload['outstanding'] = null;
            $payload['documents'] = $paths ?: null;
            $payload['manager_name'] = null;
            $payload['reject_reason'] = null;
            $payload['reviewed_at'] = null;

            $row = $this->repository->create($payload);

            $this->audit->log('loan.created', $row, [
                'employee_id' => $row->employee_id,
                'code' => $row->code,
                'amount' => $row->amount,
            ]);

            return $row;
        });
    }

    public function updateStatusForActor(
        User $actor,
        Loan $loan,
        string $status,
        ?string $rejectReason = null,
    ): Loan {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($loan, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider cette demande de prêt.');
        }

        if (! EntityStatusCatalog::contains('loans', $status, Loan::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }

        return DB::transaction(function () use ($actor, $loan, $status, $rejectReason) {
            $payload = [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'reject_reason' => $status === 'rejected' ? $rejectReason : null,
            ];

            if (in_array($status, ['approved', 'active'], true) && $loan->outstanding === null) {
                $payload['outstanding'] = (float) $loan->amount;
            }
            if ($status === 'active' && $loan->status === 'approved') {
                $payload['outstanding'] = $loan->outstanding ?? (float) $loan->amount;
            }
            if ($status === 'closed') {
                $payload['outstanding'] = 0;
            }

            $updated = $this->repository->update($loan, $payload);
            $this->audit->log('loan.status_updated', $updated, ['status' => $status]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, Loan $loan): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($loan, $companyId);
        $this->ensureSelfServiceOwns($actor, $loan);

        if ($this->isSelfServiceActor($actor) && $loan->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une demande en attente peut être annulée.',
            ]);
        }

        return DB::transaction(function () use ($loan) {
            foreach ($loan->documents ?? [] as $path) {
                Storage::disk('public')->delete($path);
            }

            $this->audit->log('loan.deleted', $loan, [
                'employee_id' => $loan->employee_id,
                'code' => $loan->code,
            ]);

            return $this->repository->delete($loan);
        });
    }

    public function findForActor(User $actor, Loan $loan): Loan
    {
        $this->ensureBelongsToCompany($loan, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $loan);

        return $loan->loadMissing('employee');
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux prêts.');
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

    private function ensureSelfServiceOwns(User $actor, Loan $loan): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $loan->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Demande de prêt introuvable.');
        }
    }

    private function ensureBelongsToCompany(Loan $loan, int $companyId): void
    {
        if ($loan->company_id !== $companyId) {
            throw new AuthorizationException('Demande de prêt introuvable.');
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

    private function assertValidType(string $type): void
    {
        if (! array_key_exists($type, Loan::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Type de prêt invalide.']);
        }
    }
}
