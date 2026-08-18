<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Models\Holiday;
use Illuminate\Database\Eloquent\Collection;

interface HolidayRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Holiday>
     */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?Holiday;

    /** @param array<string, mixed> $data */
    public function create(array $data): Holiday;

    /** @param array<string, mixed> $data */
    public function update(Holiday $holiday, array $data): Holiday;

    public function delete(Holiday $holiday): bool;
}
