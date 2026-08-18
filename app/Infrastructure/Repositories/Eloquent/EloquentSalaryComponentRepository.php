<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Payroll\Models\SalaryComponent;
use App\Domain\Payroll\Repositories\SalaryComponentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentSalaryComponentRepository implements SalaryComponentRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $companyId = null, bool $includeStatutory = false): LengthAwarePaginator
    {
        return SalaryComponent::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            // Hide inactive rows
            ->where('is_active', true)
            // Cotisations légales (is_statutory) : hors catalogue « Composants » par défaut
            ->when(! $includeStatutory, fn ($q) => $q->where('is_statutory', false))
            ->orderBy('sequence')
            ->paginate($perPage);
    }

    public function listForCompany(int $companyId, bool $includeStatutory = false): Collection
    {
        return SalaryComponent::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when(! $includeStatutory, fn ($q) => $q->where('is_statutory', false))
            ->orderBy('sequence')
            ->get();
    }

    public function find(int $id): ?SalaryComponent
    {
        return SalaryComponent::query()->find($id);
    }

    public function create(array $data): SalaryComponent
    {
        return SalaryComponent::query()->create($data);
    }

    public function update(SalaryComponent $component, array $data): SalaryComponent
    {
        $component->fill($data)->save();
        return $component->refresh();
    }

    public function delete(SalaryComponent $component): bool
    {
        return (bool) $component->delete();
    }
}
