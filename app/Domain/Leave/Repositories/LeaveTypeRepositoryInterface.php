<?php

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Models\LeaveType;
use Illuminate\Database\Eloquent\Collection;

interface LeaveTypeRepositoryInterface
{
    /** @return Collection<int, LeaveType> */
    public function listByCompany(int $companyId): Collection;

    public function findForCompany(int $id, int $companyId): ?LeaveType;

    /** @param array<string, mixed> $data */
    public function create(array $data): LeaveType;

    /** @param array<string, mixed> $data */
    public function update(LeaveType $leaveType, array $data): LeaveType;

    public function delete(LeaveType $leaveType): bool;
}
