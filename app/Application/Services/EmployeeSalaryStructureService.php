<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\Payroll\SalaryStructureData;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Payroll\Models\SalaryComponent;
use App\Domain\Payroll\Repositories\EmployeeSalaryStructureRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeSalaryStructureService
{
    public function __construct(
        private readonly EmployeeSalaryStructureRepositoryInterface $repository,
        private readonly AuditLogger $audit,
    ) {}

    /** @return Collection<int, EmployeeSalaryStructure> */
    public function listForEmployee(int $employeeId): Collection
    {
        return $this->repository->listForEmployee($employeeId);
    }

    public function find(int $id): ?EmployeeSalaryStructure
    {
        return $this->repository->find($id);
    }

    /**
     * Create a new structure version. If active and overlapping the previous active one,
     * the previous structure's effective_to is automatically closed the day before.
     */
    public function assignStructure(SalaryStructureData $data): EmployeeSalaryStructure
    {
        $employee = Employee::query()->findOrFail($data->employeeId);
        $componentIds = array_values(array_unique(array_map(
            static fn ($line) => $line->salaryComponentId,
            $data->lines,
        )));
        if ($componentIds !== []) {
            $matchingComponents = SalaryComponent::query()
                ->whereIn('id', $componentIds)
                ->where(function ($query) use ($employee) {
                    $query->whereNull('company_id')
                        ->orWhere('company_id', $employee->company_id);
                })
                ->count();

            if ($matchingComponents !== count($componentIds)) {
                throw ValidationException::withMessages([
                    'lines' => 'Un ou plusieurs composants de paie ne sont pas accessibles pour cet employé.',
                ]);
            }
        }

        return DB::transaction(function () use ($data) {
            if ($data->status === EmployeeSalaryStructure::STATUS_ACTIVE) {
                $this->repository->archivePrevious($data->employeeId, $data->effectiveFrom);
            }

            $structure = $this->repository->create([
                'employee_id' => $data->employeeId,
                'name' => $data->name,
                'base_salary' => $data->baseSalary,
                'currency' => $data->currency,
                'effective_from' => $data->effectiveFrom,
                'effective_to' => $data->effectiveTo,
                'status' => $data->status,
            ]);

            foreach ($data->lines as $line) {
                $structure->lines()->create($line->toModelArray());
            }

            $this->audit->log('salary_structure.created', $structure, [
                'employee_id' => $data->employeeId,
                'effective_from' => $data->effectiveFrom,
                'lines_count' => count($data->lines),
            ]);

            return $structure->load('lines.component');
        });
    }

    public function archive(EmployeeSalaryStructure $structure): EmployeeSalaryStructure
    {
        $updated = $this->repository->update($structure, [
            'status' => EmployeeSalaryStructure::STATUS_ARCHIVED,
            'effective_to' => $structure->effective_to ?? now()->toDateString(),
        ]);
        $this->audit->log('salary_structure.archived', $updated);

        return $updated;
    }
}
