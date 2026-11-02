<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\DTO\Payroll\PayrollContext;
use App\Application\DTO\Payroll\PayrollResult;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Domain\Payroll\Models\EmployeeSalaryStructure;
use App\Domain\Tax\Models\CompanyTax;
use App\Domain\Tax\Models\LegalTax;
use App\Engines\Exceptions\PayrollException;
use App\Engines\FormulaEngine;
use App\Engines\TaxEngine;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TaxEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');

        Schema::create('legal_taxes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->string('base_target');
            $table->decimal('employer_rate', 8, 4)->default(0);
            $table->decimal('employee_rate', 8, 4)->default(0);
            $table->decimal('ceiling', 18, 2)->nullable();
            $table->decimal('floor', 18, 2)->nullable();
            $table->text('formula')->nullable();
            $table->boolean('reduces_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sequence')->default(100);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_brackets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legal_tax_id');
            $table->decimal('min_amount', 18, 2);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->decimal('rate', 8, 4);
            $table->decimal('fixed_deduction', 18, 2)->default(0);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function test_percentage_rule_applies_floor_and_ceiling_and_records_rule_snapshot(): void
    {
        $tax = $this->createTax([
            'code' => 'SOCIAL',
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_GROSS,
            'employer_rate' => 0.2,
            'employee_rate' => 0.1,
            'floor' => 1200,
            'ceiling' => 1500,
        ]);
        $result = $this->buildResult(gross: 1000);

        $this->engine()->applyForStage($this->context(), $result, LegalTax::BASE_GROSS);

        $this->assertSame(1200.0, $result->lines[0]->baseAmount);
        $this->assertSame(120.0, $result->lines[1]->amount);
        $this->assertSame(240.0, $result->lines[0]->amount);
        $this->assertSame(880.0, $result->net);
        $this->assertSame(1200.0, $result->lines[0]->meta['rule_snapshot']['calculation_base']);
        $this->assertSame('MA', $result->lines[0]->meta['rule_snapshot']['country_code']);
        $this->assertSame($tax->id, $result->lines[0]->sourceId);
    }

    public function test_company_override_is_used_only_when_effective_for_pay_period(): void
    {
        $tax = $this->createTax([
            'code' => 'SOCIAL',
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_GROSS,
            'employee_rate' => 0.1,
        ]);
        $companyTax = new CompanyTax([
            'legal_tax_id' => $tax->id,
            'override_employee_rate' => 0.2,
            'is_active' => true,
            'effective_from' => '2026-02-01',
        ]);
        $context = $this->context('2026-01-01', '2026-01-31', [$companyTax]);
        $result = $this->buildResult(gross: 1000);

        $this->engine()->applyForStage($context, $result, LegalTax::BASE_GROSS);

        $this->assertSame(100.0, $result->lines[0]->amount);
        $this->assertSame(0.1, $result->lines[0]->meta['rule_snapshot']['employee_rate']);
    }

    public function test_progressive_rule_snapshot_contains_the_brackets_used(): void
    {
        $tax = $this->createTax([
            'code' => 'INCOME',
            'type' => LegalTax::TYPE_PROGRESSIVE,
            'base_target' => LegalTax::BASE_TAXABLE,
        ]);
        $tax->brackets()->createMany([
            ['min_amount' => 0, 'max_amount' => 500, 'rate' => 0.1, 'fixed_deduction' => 0, 'sequence' => 0],
            ['min_amount' => 500, 'max_amount' => null, 'rate' => 0.2, 'fixed_deduction' => 0, 'sequence' => 1],
        ]);
        $result = $this->buildResult(taxable: 1000);

        $this->engine()->applyForStage($this->context(), $result, LegalTax::BASE_TAXABLE);

        $this->assertSame(150.0, $result->lines[0]->amount);
        $this->assertCount(2, $result->lines[0]->meta['rule_snapshot']['brackets']);
    }

    public function test_progressive_tax_formula_can_cap_tax_as_a_share_of_the_base(): void
    {
        $tax = $this->createTax([
            'code' => 'IPR',
            'type' => LegalTax::TYPE_PROGRESSIVE,
            'base_target' => LegalTax::BASE_TAXABLE,
            'formula' => 'MIN(TAX_AMOUNT, BASE * 0.3)',
        ]);
        $tax->brackets()->createMany([
            ['min_amount' => 0, 'max_amount' => 162000, 'rate' => 0.03, 'fixed_deduction' => 0, 'sequence' => 0],
            ['min_amount' => 162001, 'max_amount' => 1800000, 'rate' => 0.15, 'fixed_deduction' => 0, 'sequence' => 1],
            ['min_amount' => 1800001, 'max_amount' => 3600000, 'rate' => 0.3, 'fixed_deduction' => 0, 'sequence' => 2],
            ['min_amount' => 3600001, 'max_amount' => null, 'rate' => 0.4, 'fixed_deduction' => 0, 'sequence' => 3],
        ]);
        $result = $this->buildResult(taxable: 7000000);

        $this->engine()->applyForStage($this->context(), $result, LegalTax::BASE_TAXABLE);

        $this->assertSame(2100000.0, $result->lines[0]->amount);
        $this->assertSame(
            'MIN(TAX_AMOUNT, BASE * 0.3)',
            $result->lines[0]->meta['rule_snapshot']['progressive_adjustment_formula'],
        );
    }

    public function test_congo_social_profile_calculates_expected_charges_without_income_tax(): void
    {
        $countryId = 6;
        $country = new Country();
        $country->id = $countryId;
        $country->code = 'CG';

        $this->createTax([
            'country_id' => $countryId,
            'code' => 'CNSS_PVID',
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_GROSS,
            'employer_rate' => 0.08,
            'employee_rate' => 0.04,
            'sequence' => 10,
        ]);
        $this->createTax([
            'country_id' => $countryId,
            'code' => 'CNSS_FAMILLE',
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_GROSS,
            'employer_rate' => 0.1003,
            'sequence' => 20,
        ]);
        $this->createTax([
            'country_id' => $countryId,
            'code' => 'CNSS_RISQUES',
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_GROSS,
            'employer_rate' => 0.0225,
            'sequence' => 30,
        ]);
        $context = $this->context();
        $context = new PayrollContext(
            employee: $context->employee,
            company: $context->company,
            country: $country,
            structure: $context->structure,
            period: $context->period,
            periodStart: $context->periodStart,
            periodEnd: $context->periodEnd,
            baseSalary: 100000,
            currency: 'XAF',
        );
        $result = $this->buildResult(base: 100000, gross: 100000, taxable: 100000, net: 100000);

        $this->engine()->applyForStage($context, $result, LegalTax::BASE_GROSS);

        $this->assertSame(20280.0, $result->totalEmployerCharges);
        $this->assertSame(4000.0, $result->totalEmployeeCharges);
        $this->assertSame(96000.0, $result->net);
        $this->assertCount(4, $result->lines);
    }

    public function test_unsupported_custom_base_fails_instead_of_falling_back_to_taxable(): void
    {
        $this->createTax([
            'code' => 'CUSTOM',
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_CUSTOM,
            'employee_rate' => 0.1,
        ]);

        $this->expectException(PayrollException::class);
        $this->expectExceptionMessage('Assiette légale « CUSTOM » non prise en charge');

        $this->engine()->applyForStage($this->context(), $this->buildResult(), LegalTax::BASE_GROSS);
    }

    /** @param array<string, mixed> $attributes */
    private function createTax(array $attributes): LegalTax
    {
        return LegalTax::query()->create(array_merge([
            'country_id' => 1,
            'name' => $attributes['code'],
            'type' => LegalTax::TYPE_PERCENT,
            'base_target' => LegalTax::BASE_GROSS,
            'employer_rate' => 0,
            'employee_rate' => 0,
            'reduces_taxable' => false,
            'is_active' => true,
            'sequence' => 10,
        ], $attributes));
    }

    private function engine(): TaxEngine
    {
        return new TaxEngine(new FormulaEngine());
    }

    private function context(
        string $periodStart = '2026-01-01',
        string $periodEnd = '2026-01-31',
        array $companyTaxes = [],
    ): PayrollContext {
        $company = new Company();
        $company->settings = [];
        $company->setRelation('companyTaxes', collect($companyTaxes));

        $country = new Country();
        $country->id = 1;
        $country->code = 'MA';

        return new PayrollContext(
            employee: new Employee(),
            company: $company,
            country: $country,
            structure: new EmployeeSalaryStructure(),
            period: Carbon::parse($periodStart)->format('Y-m'),
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            baseSalary: 1000,
            currency: 'MAD',
        );
    }

    private function buildResult(
        float $base = 1000,
        float $gross = 1000,
        float $taxable = 1000,
        float $net = 1000,
    ): PayrollResult {
        $result = new PayrollResult();
        $result->base = $base;
        $result->gross = $gross;
        $result->taxable = $taxable;
        $result->net = $net;

        return $result;
    }
}
