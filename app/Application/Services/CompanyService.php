<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Company\CompanyData;
use App\Domain\Company\Models\Company;
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
            $company = $this->repository->create($data->toModelArray());
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
            $updated = $this->repository->update($company, $data->toModelArray());
            $updated->refresh();
            $this->tenantContactUser->syncForCompany($updated, $data->contactPassword);
            $this->audit->log('company.updated', $updated, ['code' => $updated->code]);

            return $updated;
        });

        return $updated;
    }

    public function toggleStatus(Company $company, bool $isActive): Company
    {
        $updated = $this->repository->update($company, ['is_active' => $isActive]);
        $this->audit->log('company.status_changed', $updated, [
            'code' => $updated->code,
            'is_active' => $isActive,
        ]);

        return $updated;
    }

    public function delete(Company $company): bool
    {
        $this->audit->log('company.deleted', $company, ['code' => $company->code]);

        return $this->repository->delete($company);
    }
}
