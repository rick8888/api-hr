<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Platform\Models\PayrollCycle;
use App\Domain\Platform\Repositories\PayrollCycleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

final class EloquentPayrollCycleRepository implements PayrollCycleRepositoryInterface
{
    public function all(): Collection
    {
        return PayrollCycle::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function listActive(): Collection
    {
        return PayrollCycle::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?PayrollCycle
    {
        return PayrollCycle::query()->find($id);
    }

    public function create(array $attributes): PayrollCycle
    {
        return PayrollCycle::query()->create($attributes);
    }

    public function update(PayrollCycle $payrollCycle, array $attributes): PayrollCycle
    {
        $payrollCycle->fill($attributes);
        $payrollCycle->save();

        return $payrollCycle->refresh();
    }

    public function delete(PayrollCycle $payrollCycle): bool
    {
        return (bool) $payrollCycle->delete();
    }
}
