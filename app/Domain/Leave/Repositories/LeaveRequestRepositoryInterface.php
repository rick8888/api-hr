<?php

declare(strict_types=1);

namespace App\Domain\Leave\Repositories;

use App\Domain\Leave\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Collection;

interface LeaveRequestRepositoryInterface
{
    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     date?: string|null,
     *     status?: string|null,
     *     leave_type?: string|null,
     *     employee_id?: int|null,
     *     designation?: string|null
     * }  $filters
     * @return Collection<int, LeaveRequest>
     */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?LeaveRequest;

    /** @param array<string, mixed> $data */
    public function create(array $data): LeaveRequest;

    /** @param array<string, mixed> $data */
    public function update(LeaveRequest $leaveRequest, array $data): LeaveRequest;

    public function delete(LeaveRequest $leaveRequest): bool;
}
