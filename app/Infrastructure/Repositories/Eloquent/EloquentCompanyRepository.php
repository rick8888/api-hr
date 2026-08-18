<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCompanyRepository implements CompanyRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $countryId = null): LengthAwarePaginator
    {
        return Company::query()
            ->with('country')
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function find(int $id): ?Company
    {
        return Company::query()->with('country')->find($id);
    }

    public function create(array $data): Company
    {
        return Company::query()->create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $company->fill($data)->save();

        return $company->refresh()->load('country');
    }

    public function delete(Company $company): bool
    {
        return (bool) $company->delete();
    }
}
