<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\ExpenseTypeData;
use App\Domain\Expense\Models\Expense;
use App\Domain\Platform\Models\ExpenseType;
use App\Domain\Platform\Repositories\ExpenseTypeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ExpenseTypeService
{
    public function __construct(
        private readonly ExpenseTypeRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, ExpenseType> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, ExpenseType> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(ExpenseTypeData $data): ExpenseType
    {
        return DB::transaction(function () use ($data) {
            $type = $this->repository->create($data->toModelArray());
            $this->audit->log('expense_type.created', $type, ['code' => $type->code]);

            return $type;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(ExpenseType $expenseType, array $validated): ExpenseType
    {
        return DB::transaction(function () use ($expenseType, $validated) {
            $payload = array_merge($this->modelToArray($expenseType), $validated);

            if ($expenseType->is_system) {
                $payload['code'] = $expenseType->code;
            }

            $updated = $this->repository->update(
                $expenseType,
                ExpenseTypeData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('expense_type.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(ExpenseType $expenseType, bool $isActive): ExpenseType
    {
        if ($expenseType->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un type système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($expenseType, ['is_active' => $isActive]);
    }

    public function delete(ExpenseType $expenseType): bool
    {
        if ($expenseType->is_system) {
            throw ValidationException::withMessages([
                'expense_type' => 'Un type système ne peut pas être supprimé.',
            ]);
        }

        if (Expense::query()->where('type', $expenseType->code)->exists()) {
            throw ValidationException::withMessages([
                'expense_type' => 'Ce type est utilisé par des notes de frais et ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($expenseType) {
            $this->audit->log('expense_type.deleted', $expenseType, ['code' => $expenseType->code]);

            return $this->repository->delete($expenseType);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(ExpenseType $expenseType): array
    {
        return [
            'name' => $expenseType->name,
            'code' => $expenseType->code,
            'description' => $expenseType->description,
            'sort_order' => $expenseType->sort_order,
            'is_system' => $expenseType->is_system,
            'is_active' => $expenseType->is_active,
        ];
    }
}
