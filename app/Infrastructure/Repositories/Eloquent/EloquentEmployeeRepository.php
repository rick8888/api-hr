<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Eloquent;

use App\Application\Services\PayrollSettingsService;
use App\Application\Services\StatutoryComponentSyncService;
use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Domain\Payroll\Models\SalaryComponent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class EloquentEmployeeRepository implements EmployeeRepositoryInterface
{
    public function paginate(int $perPage = 15, ?int $companyId = null): LengthAwarePaginator
    {
        return Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('last_name')
            ->paginate($perPage);
    }

    public function listByCompany(int $companyId, array $filters = []): Collection
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $department = isset($filters['department']) ? trim((string) $filters['department']) : '';
        $structureStatus = isset($filters['structure_status'])
            ? trim((string) $filters['structure_status'])
            : '';

        $expectedComponentIds = $this->expectedSalaryComponentIds($companyId);

        return Employee::query()
            ->where('company_id', $companyId)
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('matricule', 'like', $like)
                        ->orWhereRaw(
                            "CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?",
                            [$like]
                        );
                });
            })
            ->when($department !== '', fn ($q) => $q->where('department', $department))
            ->when(
                in_array($structureStatus, ['complete', 'incomplete', 'none'], true),
                function ($q) use ($structureStatus, $expectedComponentIds) {
                    if ($structureStatus === 'none') {
                        $q->whereDoesntHave('salaryStructures');

                        return;
                    }

                    if ($structureStatus === 'complete') {
                        $this->applyCompleteStructureScope($q, $expectedComponentIds);

                        return;
                    }

                    // incomplete = pas prêt (incomplet + non configuré)
                    $q->where(function ($outer) use ($expectedComponentIds) {
                        $outer->whereDoesntHave('salaryStructures')
                            ->orWhere(function ($sub) use ($expectedComponentIds) {
                                $sub->whereHas('salaryStructures');
                                if ($expectedComponentIds->isEmpty()) {
                                    return;
                                }
                                $sub->whereDoesntHave('salaryStructures', function ($sq) use ($expectedComponentIds) {
                                    $this->constrainStructureToComplete($sq, $expectedComponentIds);
                                });
                            });
                    });
                }
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Composants actifs du catalogue (hors cotisations légales en mode auto).
     *
     * @return SupportCollection<int, int>
     */
    private function expectedSalaryComponentIds(int $companyId): SupportCollection
    {
        $company = Company::query()->find($companyId);
        $includeStatutory = $company
            && PayrollSettingsService::statutoryModeForCompany($company) === StatutoryComponentSyncService::MODE_MANUAL;

        return SalaryComponent::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when(! $includeStatutory, fn ($q) => $q->where('is_statutory', false))
            ->pluck('id');
    }

    /**
     * Complet = structure active contenant chaque composant catalogue attendu.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Domain\Employee\Models\Employee>  $q
     * @param  SupportCollection<int, int>  $expectedComponentIds
     */
    private function applyCompleteStructureScope($q, SupportCollection $expectedComponentIds): void
    {
        if ($expectedComponentIds->isEmpty()) {
            $q->whereRaw('0 = 1');

            return;
        }

        $q->whereHas('salaryStructures', function ($sq) use ($expectedComponentIds) {
            $this->constrainStructureToComplete($sq, $expectedComponentIds);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Domain\Payroll\Models\EmployeeSalaryStructure>  $sq
     * @param  SupportCollection<int, int>  $expectedComponentIds
     */
    private function constrainStructureToComplete($sq, SupportCollection $expectedComponentIds): void
    {
        $sq->where('status', 'active');

        foreach ($expectedComponentIds as $componentId) {
            $sq->whereHas('lines', function ($lq) use ($componentId) {
                $lq->where('salary_component_id', $componentId)
                    ->where('is_active', true);
            });
        }
    }

    public function find(int $id): ?Employee
    {
        return Employee::query()->find($id);
    }

    public function findForCompany(int $id, int $companyId): ?Employee
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->find($id);
    }

    public function create(array $data): Employee
    {
        return Employee::query()->create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->fill($data)->save();

        return $employee->refresh();
    }

    public function delete(Employee $employee): bool
    {
        return (bool) $employee->delete();
    }
}

