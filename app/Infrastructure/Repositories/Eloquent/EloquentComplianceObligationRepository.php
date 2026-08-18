<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Compliance\Models\ComplianceObligation;
use App\Domain\Compliance\Repositories\ComplianceObligationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentComplianceObligationRepository implements ComplianceObligationRepositoryInterface
{
    public function paginate(
        int $perPage = 15,
        ?int $countryId = null,
        ?string $type = null,
        ?bool $isActive = null,
    ): LengthAwarePaginator {
        return ComplianceObligation::query()
            ->with('country')
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive))
            ->orderBy('country_id')
            ->orderBy('code')
            ->paginate($perPage);
    }

    public function find(int $id): ?ComplianceObligation
    {
        return ComplianceObligation::query()->with('country')->find($id);
    }

    public function create(array $data): ComplianceObligation
    {
        $obligation = ComplianceObligation::query()->create($data);

        return $obligation->load('country');
    }

    public function update(ComplianceObligation $obligation, array $data): ComplianceObligation
    {
        $obligation->fill($data)->save();

        return $obligation->refresh()->load('country');
    }

    public function delete(ComplianceObligation $obligation): bool
    {
        return (bool) $obligation->delete();
    }
}
