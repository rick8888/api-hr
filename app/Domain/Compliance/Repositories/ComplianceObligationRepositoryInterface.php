<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Repositories;

use App\Domain\Compliance\Models\ComplianceObligation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ComplianceObligationRepositoryInterface
{
    public function paginate(
        int $perPage = 15,
        ?int $countryId = null,
        ?string $type = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator;

    public function find(int $id): ?ComplianceObligation;

    /** @param array<string, mixed> $data */
    public function create(array $data): ComplianceObligation;

    /** @param array<string, mixed> $data */
    public function update(ComplianceObligation $obligation, array $data): ComplianceObligation;

    public function delete(ComplianceObligation $obligation): bool;
}
