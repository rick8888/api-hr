<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Tax\Models\CompanyTax;
use App\Domain\Tax\Models\LegalTax;
use Illuminate\Database\Seeder;

final class PayrollCountryTestDataSeeder extends Seeder
{
    public function run(): void
    {
        DemoEnvironmentGuard::ensureAllowed(self::class);

        $this->seedCountryEmployee(
            countryCode: 'CD',
            companyCode: 'PAYROLL-TEST-CD',
            companyName: 'Entreprise test paie — RDC',
            employeeCode: 'PAYROLL-TEST-CD-001',
            firstName: 'Employé test',
            lastName: 'RDC',
            salary: 10_000_000,
            currency: 'CDF',
        );

        $this->seedCountryEmployee(
            countryCode: 'CG',
            companyCode: 'PAYROLL-TEST-CG',
            companyName: 'Entreprise test paie — Congo-Brazzaville',
            employeeCode: 'PAYROLL-TEST-CG-001',
            firstName: 'Employé test',
            lastName: 'Congo',
            salary: 100_000,
            currency: 'XAF',
        );
    }

    private function seedCountryEmployee(
        string $countryCode,
        string $companyCode,
        string $companyName,
        string $employeeCode,
        string $firstName,
        string $lastName,
        float $salary,
        string $currency,
    ): void {
        $country = Country::query()->where('code', $countryCode)->firstOrFail();

        $company = Company::query()->updateOrCreate(
            ['code' => $companyCode],
            [
                'country_id' => $country->id,
                'name' => $companyName,
                'legal_name' => $companyName,
                'currency' => $currency,
                'address' => 'Données locales réservées aux tests de paie',
                'settings' => [
                    'test_fixture' => true,
                    'headcount_range' => $countryCode === 'CD' ? '11-50' : null,
                    'industry' => 'Services privés',
                ],
                'is_active' => true,
            ],
        );

        if ($countryCode === 'CD') {
            $inpp = LegalTax::query()
                ->where('country_id', $country->id)
                ->where('code', 'INPP')
                ->firstOrFail();

            CompanyTax::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'legal_tax_id' => $inpp->id,
                ],
                [
                    'override_employer_rate' => 0.03,
                    'is_active' => true,
                    'effective_from' => '2026-11-01',
                    'effective_to' => null,
                ],
            );
        }

        $employee = Employee::query()->updateOrCreate(
            ['matricule' => $employeeCode],
            [
                'company_id' => $company->id,
                'country_id' => $country->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => strtolower(str_replace('-', '.', $employeeCode)).'@example.test',
                'job_title' => 'Salarié fictif — test paie',
                'department' => 'TEST',
                'contract_type' => 'CDI',
                'hired_at' => '2026-11-01',
                'base_salary' => $salary,
                'currency' => $currency,
                'metadata' => [
                    'test_fixture' => true,
                    'purpose' => 'Aperçu de paie multi-pays, novembre 2026',
                ],
                'is_active' => true,
            ],
        );

        EmployeeSalaryStructure::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'name' => 'Structure TEST — novembre 2026',
            ],
            [
                'base_salary' => $salary,
                'currency' => $currency,
                'effective_from' => '2026-11-01',
                'effective_to' => null,
                'status' => EmployeeSalaryStructure::STATUS_ACTIVE,
                'metadata' => ['test_fixture' => true],
            ],
        );
    }
}
