<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Models\Regularization;
use Illuminate\Database\Eloquent\Collection;

interface RegularizationRepositoryInterface
{
    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     status?: string|null,
     *     type?: string|null,
     *     employee_id?: int|null
     * }  $filters
     * @return Collection<int, Regularization>
     */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Regularization;

    /** @param array<string, mixed> $data */
    public function create(array $data): Regularization;

    /** @param array<string, mixed> $data */
    public function update(Regularization $regularization, array $data): Regularization;

    public function delete(Regularization $regularization): bool;
}
