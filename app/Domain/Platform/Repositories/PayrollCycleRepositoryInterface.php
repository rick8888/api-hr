<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Platform\Models\PayrollCycle;
use Illuminate\Database\Eloquent\Collection;

interface PayrollCycleRepositoryInterface
{
    /** @return Collection<int, PayrollCycle> */
    public function all(): Collection;

    /** @return Collection<int, PayrollCycle> */
    public function listActive(): Collection;

    public function find(int $id): ?PayrollCycle;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): PayrollCycle;

    /** @param array<string, mixed> $attributes */
    public function update(PayrollCycle $payrollCycle, array $attributes): PayrollCycle;

    public function delete(PayrollCycle $payrollCycle): bool;
}
