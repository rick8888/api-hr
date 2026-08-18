<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Payroll\Models\PayrollModifier;
use App\Domain\Payroll\Repositories\PayrollModifierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PayrollModifierService
{
    public function __construct(
        private readonly PayrollModifierRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    public function paginate(int $perPage = 50, ?int $companyId = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $companyId);
    }

    public function find(int $id): ?PayrollModifier
    {
        return $this->repository->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): PayrollModifier
    {
        $modifier = $this->repository->create($data);
        $this->audit->log('payroll_modifier.created', $modifier, ['code' => $modifier->code]);

        return $modifier;
    }

    /** @param array<string, mixed> $data */
    public function update(PayrollModifier $modifier, array $data): PayrollModifier
    {
        $updated = $this->repository->update($modifier, $data);
        $this->audit->log('payroll_modifier.updated', $updated, ['code' => $updated->code]);

        return $updated;
    }

    public function delete(PayrollModifier $modifier): bool
    {
        $this->audit->log('payroll_modifier.deleted', $modifier, ['code' => $modifier->code]);

        return $this->repository->delete($modifier);
    }
}
