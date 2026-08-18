<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Expense\ExpenseData;
use App\Domain\Employee\Models\Employee;
use App\Domain\Expense\Models\Expense;
use App\Domain\Expense\Repositories\ExpenseRepositoryInterface;
use App\Domain\Platform\Models\ExpenseType;
use App\Domain\Platform\Support\EntityStatusCatalog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $repository,
        private readonly EmployeeProfileLinker $employeeProfiles,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Expense>
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
        ExpenseData $data,
        ?UploadedFile $receipt = null,
    ): Expense {
        $companyId = $this->resolveCompanyId($actor);

        if ($this->isSelfServiceActor($actor) || $data->employeeId <= 0) {
            $employee = $this->resolveEmployeeForActor($actor);
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun profil employé lié à votre compte.',
                ]);
            }
            $data = ExpenseData::fromArray([
                ...$data->toModelArray(),
                'employee_id' => $employee->id,
            ]);
        }

        $this->ensureEmployeeBelongsToCompany($data->employeeId, $companyId);
        $this->assertValidType($data->type);

        return DB::transaction(function () use ($actor, $companyId, $data, $receipt) {
            $payload = $data->toModelArray();
            $payload['company_id'] = $companyId;
            $payload['status'] = 'pending';
            $payload['manager_name'] = null;
            $payload['reject_reason'] = null;
            $payload['reviewed_at'] = null;

            if ($receipt !== null) {
                $payload['receipt_path'] = $receipt->store(
                    "expenses/{$companyId}",
                    'public',
                );
            }

            $row = $this->repository->create($payload);

            $this->audit->log('expense.created', $row, [
                'employee_id' => $row->employee_id,
                'type' => $row->type,
                'amount' => $row->amount,
            ]);

            return $row;
        });
    }

    public function updateStatusForActor(
        User $actor,
        Expense $expense,
        string $status,
        ?string $rejectReason = null,
    ): Expense {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($expense, $companyId);

        if ($this->isSelfServiceActor($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider cette dépense.');
        }

        if (! EntityStatusCatalog::contains('expenses', $status, Expense::STATUSES)) {
            throw ValidationException::withMessages(['status' => 'Statut invalide.']);
        }

        if ($expense->status !== 'pending' && ! in_array($status, [$expense->status, 'reimbursed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Transition de statut non autorisée.',
            ]);
        }

        if ($status === 'reimbursed' && ! in_array($expense->status, ['approved', 'reimbursed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Seule une dépense approuvée peut être remboursée.',
            ]);
        }

        return DB::transaction(function () use ($actor, $expense, $status, $rejectReason) {
            $payload = [
                'status' => $status,
                'manager_name' => $status === 'pending' ? null : ($actor->name ?? $actor->email),
                'reviewed_at' => $status === 'pending' ? null : now()->toDateTimeString(),
                'reject_reason' => $status === 'rejected' ? $rejectReason : null,
            ];

            $updated = $this->repository->update($expense, $payload);
            $this->audit->log('expense.status_updated', $updated, ['status' => $status]);

            return $updated;
        });
    }

    public function deleteForActor(User $actor, Expense $expense): bool
    {
        $companyId = $this->resolveCompanyId($actor);
        $this->ensureBelongsToCompany($expense, $companyId);
        $this->ensureSelfServiceOwns($actor, $expense);

        if ($this->isSelfServiceActor($actor) && $expense->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Seule une dépense en attente peut être annulée.',
            ]);
        }

        return DB::transaction(function () use ($expense) {
            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }

            $this->audit->log('expense.deleted', $expense, [
                'employee_id' => $expense->employee_id,
            ]);

            return $this->repository->delete($expense);
        });
    }

    public function findForActor(User $actor, Expense $expense): Expense
    {
        $this->ensureBelongsToCompany($expense, $this->resolveCompanyId($actor));
        $this->ensureSelfServiceOwns($actor, $expense);

        return $expense->loadMissing('employee');
    }

    private function resolveCompanyId(User $actor): int
    {
        if (
            $actor->company_id === null
            || ! in_array($actor->role, ['tenant', 'admin', 'team_manager', 'employe'], true)
        ) {
            throw new AuthorizationException('Accès non autorisé aux notes de frais.');
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

    private function ensureSelfServiceOwns(User $actor, Expense $expense): void
    {
        if (! $this->isSelfServiceActor($actor)) {
            return;
        }

        $employee = $this->resolveEmployeeForActor($actor);
        if ($employee === null || (int) $expense->employee_id !== (int) $employee->id) {
            throw new AuthorizationException('Note de frais introuvable.');
        }
    }

    private function ensureBelongsToCompany(Expense $expense, int $companyId): void
    {
        if ($expense->company_id !== $companyId) {
            throw new AuthorizationException('Note de frais introuvable.');
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
        $valid = Schema::hasTable('expense_types')
            ? ExpenseType::query()->where('code', $type)->where('is_active', true)->exists()
            : in_array($type, Expense::TYPES, true);

        if (! $valid) {
            throw ValidationException::withMessages(['type' => 'Type de dépense invalide.']);
        }
    }
}
