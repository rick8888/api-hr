<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Tax\Models\LegalTax;
use App\Domain\Tax\Repositories\LegalTaxRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentLegalTaxRepository implements LegalTaxRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $countryId = null): LengthAwarePaginator
    {
        return LegalTax::query()
            ->with(['brackets', 'country'])
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->orderBy('country_id')
            ->orderBy('sequence')
            ->paginate($perPage);
    }

    public function listForCountry(int $countryId): Collection
    {
        return LegalTax::query()
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->with('brackets')
            ->orderBy('sequence')
            ->get();
    }

    public function find(int $id): ?LegalTax
    {
        return LegalTax::query()->with(['brackets', 'country'])->find($id);
    }

    public function create(array $data): LegalTax
    {
        $tax = LegalTax::query()->create($data);

        return $tax->load(['brackets', 'country']);
    }

    public function update(LegalTax $tax, array $data): LegalTax
    {
        $tax->fill($data)->save();

        return $tax->refresh()->load(['brackets', 'country']);
    }

    public function delete(LegalTax $tax): bool
    {
        return (bool) $tax->delete();
    }
}
