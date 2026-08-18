<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Models\Shift;
use Illuminate\Database\Eloquent\Collection;

interface ShiftRepositoryInterface
{
    /** @return Collection<int, Shift> */
    public function listByCompany(int $companyId): Collection;

    public function findForCompany(int $id, int $companyId): ?Shift;

    /** @param array<string, mixed> $data */
    public function create(array $data): Shift;

    /** @param array<string, mixed> $data */
    public function update(Shift $shift, array $data): Shift;

    public function delete(Shift $shift): bool;
}
