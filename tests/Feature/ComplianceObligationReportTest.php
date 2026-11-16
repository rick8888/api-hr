<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Country;
use App\Domain\Company\Models\Company;
use App\Domain\Compliance\Models\ComplianceObligation;
use App\Domain\Compliance\Models\Declaration;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\Payroll;
use App\Domain\Payroll\Models\PayrollLine;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ComplianceObligationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_read_only_the_compliance_calendar_for_its_company_country(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $companyCountryId = $admin->company?->country_id;
        $otherCountry = Country::query()
            ->whereKeyNot($companyCountryId)
            ->firstOrFail();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/compliance-obligations?country_id='.$otherCountry->id.'&is_active=true&per_page=200')
            ->assertOk()
            ->assertJsonPath('data.0.country_id', $companyCountryId);

        $this->assertSame(
            [$companyCountryId],
            array_values(array_unique(array_column($response->json('data'), 'country_id')))
        );
    }

    public function test_company_admin_can_get_monthly_amounts_by_obligation_and_download_the_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $company = $admin->company;
        $employee = Employee::query()
            ->where('company_id', $company->id)
            ->firstOrFail();

        $obligation = ComplianceObligation::query()->create([
            'country_id' => $company->country_id,
            'code' => 'TEST_PAYROLL_REPORT',
            'name' => 'Cotisations de test',
            'type' => ComplianceObligation::TYPE_SOCIAL,
            'authority' => 'Organisme test',
            'frequency' => ComplianceObligation::FREQUENCY_MONTHLY,
            'reference_period' => ComplianceObligation::REF_PREVIOUS_MONTH,
            'deadline_day_of_month' => 15,
            'is_active' => true,
            'settings' => ['linked_rule_codes' => ['TEST_COT']],
        ]);
        $payroll = Payroll::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'country_id' => $company->country_id,
            'period' => '2099-01',
            'period_start' => '2099-01-01',
            'period_end' => '2099-01-31',
            'currency' => 'MAD',
            'status' => Payroll::STATUS_VALIDATED,
        ]);
        $payroll->lines()->createMany([
            [
                'kind' => PayrollLine::KIND_TAX_EMPLOYEE,
                'code' => 'TEST_COT',
                'label' => 'Part salarié',
                'type' => PayrollLine::TYPE_DEDUCTION,
                'amount' => 12.50,
            ],
            [
                'kind' => PayrollLine::KIND_TAX_EMPLOYER,
                'code' => 'TEST_COT_ER',
                'label' => 'Part employeur',
                'type' => PayrollLine::TYPE_INFO,
                'amount' => 30.00,
            ],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/compliance-declarations/report?period=2099-01&company_id='.(int) $company->id)
            ->assertOk()
            ->assertJsonPath('data.payroll_count', 1)
            ->assertJsonPath('data.company.id', $company->id);

        $row = collect($response->json('data.obligations'))
            ->firstWhere('id', $obligation->id);
        $this->assertSame(12.5, $row['amounts'][0]['employee']);
        $this->assertEquals(30, $row['amounts'][0]['employer']);
        $this->assertEquals(42.5, $row['amounts'][0]['total']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/compliance-declarations/payment', [
                'period' => '2099-01',
                'obligation_id' => $obligation->id,
                'is_paid' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.paid_by', $admin->name);

        $paidReport = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/compliance-declarations/report?period=2099-01')
            ->assertOk();
        $paidRow = collect($paidReport->json('data.obligations'))->firstWhere('id', $obligation->id);
        $this->assertTrue($paidRow['payment']['is_paid']);
        $this->assertNotNull($paidRow['payment']['paid_at']);

        $otherCompany = Company::query()->whereKeyNot($company->id)->firstOrFail();
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/compliance-declarations/report?period=2099-01&company_id='.$otherCompany->id)
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id);

        $this->actingAs($admin, 'sanctum')
            ->get('/api/v1/compliance-declarations/report/pdf?period=2099-01')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/compliance-declarations/payment', [
                'period' => '2099-01',
                'obligation_id' => $obligation->id,
                'is_paid' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_paid', false);

        $this->assertNull(Declaration::query()
            ->where('company_id', $company->id)
            ->where('compliance_obligation_id', $obligation->id)
            ->where('period', '2099-01')
            ->firstOrFail()
            ->paid_at);
    }
}
