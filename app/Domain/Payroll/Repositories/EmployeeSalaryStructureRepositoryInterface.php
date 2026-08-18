<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use Illuminate\Support\Collection;

interface EmployeeSalaryStructureRepositoryInterface
{
    /** @return Collection<int, EmployeeSalaryStructure> */
    public function listForEmployee(int $employeeId): Collection;

    public function find(int $id): ?EmployeeSalaryStructure;

    public function findActiveForPeriod(int $employeeId, string $period): ?EmployeeSalaryStructure;

    /** @param array<string, mixed> $data */
    public function create(array $data): EmployeeSalaryStructure;

    /** @param array<string, mixed> $data */
    public function update(EmployeeSalaryStructure $structure, array $data): EmployeeSalaryStructure;

    public function archivePrevious(int $employeeId, string $effectiveFrom): void;

    public function delete(EmployeeSalaryStructure $structure): bool;
}
