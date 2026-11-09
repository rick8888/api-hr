<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Company\CompanyData;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CompanyService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $repository,
        private readonly AuditLogger $audit,
        private readonly TenantContactUserService $tenantContactUser,
    ) {}

    public function paginate(int $perPage = 15, ?int $countryId = null): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $countryId);
    }

    public function find(int $id): ?Company
    {
        return $this->repository->find($id);
    }

    public function create(CompanyData $data): Company
    {
        $company = DB::transaction(function () use ($data): Company {
            $payload = $data->toModelArray();
            $country = Country::query()->findOrFail($data->countryId);
            $payload['currency'] = strtoupper((string) (
                $data->currency ?: $country->currency ?: config('currency.default', 'USD')
            ));
            $company = $this->repository->create($payload);
            $company->refresh();
            $this->tenantContactUser->syncForCompany($company, $data->contactPassword);
            $this->audit->log('company.created', $company, ['code' => $company->code]);

            return $company->load('country');
        });

        return $company;
    }

    public function update(Company $company, CompanyData $data): Company
    {
        $updated = DB::transaction(function () use ($company, $data): Company {
            $payload = $data->toModelArray();
            if ($data->currency === null || trim($data->currency) === '') {
                $payload['currency'] = $data->countryId !== $company->country_id || ! $company->currency
                    ? (Country::query()->findOrFail($data->countryId)->currency ?: config('currency.default', 'USD'))
                    : $company->currency;
            } else {
                $payload['currency'] = strtoupper($data->currency);
            }
            $updated = $this->repository->update($company, $payload);
            $updated->refresh();
            if (! $updated->is_active) {
                $this->revokeCompanyTokens($updated);
            }
            $this->tenantContactUser->syncForCompany($updated, $data->contactPassword);
            $this->audit->log('company.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });

        return $updated;
    }

    public function toggleStatus(Company $company, bool $isActive): Company
    {
        return DB::transaction(function () use ($company, $isActive): Company {
            $updated = $this->repository->update($company, ['is_active' => $isActive]);
            if (! $isActive) {
                $this->revokeCompanyTokens($updated);
            }
            $this->audit->log('company.status_changed', $updated, [
                'code' => $updated->code,
                'is_active' => $isActive,
            ]);

            return $updated;
        });
    }

    public function delete(Company $company): bool
    {
        $this->audit->log('company.deleted', $company, ['code' => $company->code]);

        return $this->repository->delete($company);
    }

    private function revokeCompanyTokens(Company $company): void
    {
        $company->users()
            ->where(fn ($query) => $query->where('role', '<>', 'tenant')->orWhereNull('role'))
            ->each(
                static fn ($user) => $user->tokens()->delete()
            );
    }
}
