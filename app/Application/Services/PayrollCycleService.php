<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Platform\PayrollCycleData;
use App\Domain\Platform\Models\PayrollCycle;
use App\Domain\Platform\Repositories\PayrollCycleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayrollCycleService
{
    public function __construct(
        private readonly PayrollCycleRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return Collection<int, PayrollCycle> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    /** @return Collection<int, PayrollCycle> */
    public function listActive(): Collection
    {
        return $this->repository->listActive();
    }

    public function create(PayrollCycleData $data): PayrollCycle
    {
        return DB::transaction(function () use ($data) {
            $cycle = $this->repository->create($data->toModelArray());
            $this->audit->log('payroll_cycle.created', $cycle, ['code' => $cycle->code]);

            return $cycle;
        });
    }

    /** @param array<string, mixed> $validated */
    public function update(PayrollCycle $payrollCycle, array $validated): PayrollCycle
    {
        return DB::transaction(function () use ($payrollCycle, $validated) {
            $payload = array_merge($this->modelToArray($payrollCycle), $validated);

            if ($payrollCycle->is_system) {
                $payload['code'] = $payrollCycle->code;
            }

            $updated = $this->repository->update(
                $payrollCycle,
                PayrollCycleData::fromArray($payload)->toModelArray(),
            );

            $this->audit->log('payroll_cycle.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });
    }

    public function toggleStatus(PayrollCycle $payrollCycle, bool $isActive): PayrollCycle
    {
        if ($payrollCycle->is_system && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Un cycle système ne peut pas être désactivé.',
            ]);
        }

        return $this->update($payrollCycle, ['is_active' => $isActive]);
    }

    public function delete(PayrollCycle $payrollCycle): bool
    {
        if ($payrollCycle->is_system) {
            throw ValidationException::withMessages([
                'payroll_cycle' => 'Un cycle système ne peut pas être supprimé.',
            ]);
        }

        return DB::transaction(function () use ($payrollCycle) {
            $this->audit->log('payroll_cycle.deleted', $payrollCycle, ['code' => $payrollCycle->code]);

            return $this->repository->delete($payrollCycle);
        });
    }

    /** @return array<string, mixed> */
    private function modelToArray(PayrollCycle $payrollCycle): array
    {
        return [
            'name' => $payrollCycle->name,
            'code' => $payrollCycle->code,
            'description' => $payrollCycle->description,
            'sort_order' => $payrollCycle->sort_order,
            'is_system' => $payrollCycle->is_system,
            'is_active' => $payrollCycle->is_active,
        ];
    }
}
