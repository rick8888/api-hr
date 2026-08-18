<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Repositories;

use App\Domain\Recruitment\Models\JobOpening;
use Illuminate\Database\Eloquent\Collection;

interface JobOpeningRepositoryInterface
{
    /** @param array<string, mixed> $filters */
    public function listByCompany(int $companyId, array $filters = []): Collection;

    public function findForCompany(int $id, int $companyId): ?JobOpening;

    /** @param array<string, mixed> $data */
    public function create(array $data): JobOpening;

    /** @param array<string, mixed> $data */
    public function update(JobOpening $jobOpening, array $data): JobOpening;

    public function delete(JobOpening $jobOpening): bool;
}
