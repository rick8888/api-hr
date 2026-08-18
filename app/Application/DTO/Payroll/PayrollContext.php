<?php

declare(strict_types=1);

namespace App\Application\DTO\Payroll;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;

/**
 * Snapshot of all the inputs needed to run a payroll computation.
 *
 * Immutable by convention. The engines should not mutate this object;
 * they accumulate results in PayrollResult instead.
 */
final class PayrollContext
{
    public function __construct(
        public readonly Employee $employee,
        public readonly Company $company,
        public readonly Country $country,
        public readonly EmployeeSalaryStructure $structure,
        public readonly string $period,           // YYYY-MM
        public readonly string $periodStart,      // YYYY-MM-DD
        public readonly string $periodEnd,        // YYYY-MM-DD
        public readonly float $baseSalary,
        public readonly string $currency,
    ) {
    }
}
