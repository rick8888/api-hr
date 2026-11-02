<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Services\TenantContactUserService;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Payroll\Models\PayrollModifier;
use App\Domain\Payroll\Models\SalaryComponent;
use Illuminate\Database\Seeder;

/**
 * Seeds a complete working payroll example for Morocco.
 * Running this seeder gives you a company + an employee with a full salary
 * structure (base + bonuses + deductions) ready to compute via the engine.
 */
class DemoSeeder extends Seeder
{
    private const TENANT_PASSWORD = 'password123';

    public function run(): void
    {
        $morocco = Country::query()->where('code', 'MA')->firstOrFail();

        $company = Company::query()->updateOrCreate(
            ['code' => 'ACME'],
            [
                'country_id' => $morocco->id,
                'name' => 'Acme Maroc SARL',
                'legal_name' => 'Acme Maroc SARL',
                'tax_id' => 'IF-12345678',
                'registration_number' => 'RC-456789',
                'email' => 'contact@acme-maroc.demo',
                'phone' => '+212 5 22 00 00 01',
                'currency' => 'MAD',
                'is_active' => true,
                'settings' => [
                    'subdomain' => 'acme-maroc',
                    'legal_form' => 'SARL',
                    'vat_number' => 'IF-12345678',
                    'industry' => 'Technologies',
                    'language' => 'fr',
                    'timezone' => 'Africa/Casablanca',
                    'headcount_range' => '11-50',
                    'plan' => 'Business',
                    'address' => [
                        'street' => '45 Bd Mohammed V',
                        'city' => 'Casablanca',
                        'postal_code' => '20000',
                        'state' => 'Casablanca-Settat',
                    ],
                    'primary_contact' => [
                        'title' => 'M.',
                        'first_name' => 'Youssef',
                        'last_name' => 'Benali',
                        'job_title' => 'DRH',
                        'email' => 'contact@acme-maroc.demo',
                        'phone' => '+212 5 22 00 00 01',
                        'mobile' => '+212 6 11 22 33 44',
                        'language' => 'fr',
                        'role' => 'tenant',
                    ],
                ],
            ]
        );

        app(TenantContactUserService::class)->syncForCompany($company, self::TENANT_PASSWORD);

        $components = [
            [
                'code' => 'PRIME_TRANSPORT',
                'name' => 'Prime de transport',
                'type' => SalaryComponent::TYPE_EARNING,
                'calculation_type' => SalaryComponent::CALC_FIXED,
                'default_value' => 500.00,
                'is_taxable' => false,
                'affects_gross' => true,
                'affects_net' => true,
                'sequence' => 10,
            ],
            [
                'code' => 'PRIME_ANCIENNETE',
                'name' => 'Prime d\'ancienneté',
                'type' => SalaryComponent::TYPE_EARNING,
                'calculation_type' => SalaryComponent::CALC_PERCENT,
                'default_value' => 0.05,
                'base_target' => 'BASE',
                'is_taxable' => true,
                'affects_gross' => true,
                'affects_net' => true,
                'sequence' => 20,
            ],
            [
                'code' => 'PRIME_LOGEMENT',
                'name' => 'Indemnité de logement',
                'type' => SalaryComponent::TYPE_EARNING,
                'calculation_type' => SalaryComponent::CALC_FORMULA,
                'formula' => 'MIN(BASE_SALARY * 0.10, 2000)',
                'is_taxable' => true,
                'affects_gross' => true,
                'affects_net' => true,
                'sequence' => 30,
            ],
            [
                'code' => 'AVANCE_SALAIRE',
                'name' => 'Avance sur salaire',
                'type' => SalaryComponent::TYPE_DEDUCTION,
                'calculation_type' => SalaryComponent::CALC_FIXED,
                'default_value' => 0,
                'is_taxable' => false,
                'affects_gross' => false,
                'affects_net' => true,
                'sequence' => 200,
            ],
        ];

        $componentModels = [];
        foreach ($components as $c) {
            $componentModels[$c['code']] = SalaryComponent::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $c['code']],
                array_merge($c, ['country_id' => $morocco->id, 'is_active' => true]),
            );
        }

        $employee = Employee::query()->updateOrCreate(
            ['matricule' => 'EMP-0001'],
            [
                'company_id' => $company->id,
                'country_id' => $morocco->id,
                'first_name' => 'Yassine',
                'last_name' => 'Benali',
                'email' => 'yassine.benali@example.ma',
                'job_title' => 'Développeur senior',
                'department' => 'Engineering',
                'contract_type' => 'CDI',
                'hired_at' => '2023-01-15',
                'national_id' => 'CIN-AB123456',
                'social_security_number' => 'CNSS-987654',
                'tax_number' => 'TAX-MA-1122',
                'base_salary' => 12000,
                'currency' => 'MAD',
                'is_active' => true,
            ]
        );

        // Drop any previous structure to rebuild deterministically
        EmployeeSalaryStructure::query()
            ->where('employee_id', $employee->id)
            ->forceDelete();

        $structure = EmployeeSalaryStructure::query()->create([
            'employee_id' => $employee->id,
            'name' => 'Structure 2026',
            'base_salary' => 12000,
            'currency' => 'MAD',
            'effective_from' => '2026-01-01',
            'status' => EmployeeSalaryStructure::STATUS_ACTIVE,
        ]);

        $structureLines = [
            ['code' => 'PRIME_TRANSPORT',  'sequence' => 10],
            ['code' => 'PRIME_ANCIENNETE', 'sequence' => 20, 'rate_override' => 0.10],
            ['code' => 'PRIME_LOGEMENT',   'sequence' => 30],
            ['code' => 'AVANCE_SALAIRE',   'sequence' => 200, 'amount_override' => 1000],
        ];

        foreach ($structureLines as $line) {
            $component = $componentModels[$line['code']];
            $structure->lines()->create([
                'salary_component_id' => $component->id,
                'amount_override' => $line['amount_override'] ?? null,
                'rate_override' => $line['rate_override'] ?? null,
                'sequence' => $line['sequence'],
                'is_active' => true,
            ]);
        }

        // Bonus exceptionnel applied on NET, only for April 2026.
        PayrollModifier::query()->updateOrCreate(
            ['code' => 'BONUS_RAMADAN', 'employee_id' => $employee->id],
            [
                'scope' => PayrollModifier::SCOPE_EMPLOYEE,
                'company_id' => $company->id,
                'name' => 'Prime exceptionnelle Ramadan',
                'target' => PayrollModifier::TARGET_NET,
                'type' => PayrollModifier::TYPE_FIXED,
                'value' => 1500,
                'sign' => '+',
                'period_from' => '2026-04',
                'period_to' => '2026-04',
                'is_active' => true,
                'sequence' => 50,
            ]
        );
    }
}
