<?php

declare(strict_types=1);

namespace App\Domain\Tax\Repositories;

use App\Domain\Tax\Models\LegalTax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface LegalTaxRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $countryId = null): LengthAwarePaginator;

    /** @return Collection<int, LegalTax> */
    public function listForCountry(int $countryId): Collection;

    public function find(int $id): ?LegalTax;

    /** @param array<string, mixed> $data */
    public function create(array $data): LegalTax;

    /** @param array<string, mixed> $data */
    public function update(LegalTax $tax, array $data): LegalTax;

    public function delete(LegalTax $tax): bool;
}
