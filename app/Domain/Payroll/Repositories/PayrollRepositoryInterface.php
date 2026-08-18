<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Repositories;

use App\Domain\Payroll\Models\Payroll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PayrollRepositoryInterface
{
    /**
     * @param  array{
     *   period?: string|null,
     *   status?: string|array|null,
     *   employee_id?: int|null,
     *   department?: string|null,
     *   search?: string|null
     * }  $filters
     */
    public function paginate(int $perPage = 15, ?int $companyId = null, array $filters = []): LengthAwarePaginator;

    public function find(int $id): ?Payroll;

    public function findByEmployeeAndPeriod(int $employeeId, string $period): ?Payroll;

    /** @param array<string, mixed> $data */
    public function create(array $data): Payroll;

    /** @param array<string, mixed> $data */
    public function update(Payroll $payroll, array $data): Payroll;

    public function delete(Payroll $payroll): bool;
}
