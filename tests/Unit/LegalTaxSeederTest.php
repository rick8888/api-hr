<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Company\Models\Country;
use App\Domain\Tax\Models\LegalTax;
use Database\Seeders\ProvisionalPayrollProfilesSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LegalTaxSeederTest extends TestCase
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

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('currency');
            $table->string('locale');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('legal_taxes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
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

    public function test_provisional_country_profiles_are_seeded_active_with_brackets(): void
    {
        (new ProvisionalPayrollProfilesSeeder())->run();

        $senegal = Country::query()->where('code', 'SN')->firstOrFail();
        $ivoryCoast = Country::query()->where('code', 'CIV')->firstOrFail();
        $republicOfCongo = Country::query()->where('code', 'CG')->firstOrFail();

        $this->assertSame('XOF', $senegal->currency);
        $this->assertSame(4, LegalTax::query()->where('country_id', $senegal->id)->count());
        $this->assertSame(6, LegalTax::query()->where('country_id', $ivoryCoast->id)->count());
        $this->assertSame(3, LegalTax::query()->where('country_id', $republicOfCongo->id)->count());
        $this->assertSame(
            [
                'CNSS_PVID' => [0.08, 0.04],
                'CNSS_FAMILLE' => [0.1003, 0.0],
                'CNSS_RISQUES' => [0.0225, 0.0],
            ],
            LegalTax::query()
                ->where('country_id', $republicOfCongo->id)
                ->orderBy('sequence')
                ->get()
                ->mapWithKeys(fn (LegalTax $tax): array => [
                    $tax->code => [(float) $tax->employer_rate, (float) $tax->employee_rate],
                ])
                ->all(),
        );
        $this->assertSame(
            3,
            LegalTax::query()
                ->where('country_id', $republicOfCongo->id)
                ->where('is_active', true)
                ->count(),
        );
        $this->assertSame(0, LegalTax::query()->where('country_id', Country::query()->where('code', 'MA')->value('id'))->count());
        (new \Database\Seeders\LegalTaxSeeder())->seedCountries(['CD']);
        $democraticRepublicOfCongo = Country::query()->where('code', 'CD')->firstOrFail();
        $this->assertSame(4, LegalTax::query()->where('country_id', $democraticRepublicOfCongo->id)->count());
        $this->assertSame(
            'MIN(TAX_AMOUNT, BASE * 0.3)',
            LegalTax::query()
                ->where('country_id', $democraticRepublicOfCongo->id)
                ->where('code', 'IPR')
                ->value('formula'),
        );
        $this->assertSame(
            13,
            LegalTax::query()
                ->whereIn('country_id', [$senegal->id, $ivoryCoast->id, $republicOfCongo->id])
                ->where('is_active', true)
                ->count(),
        );
        $this->assertSame(
            12,
            DB::table('tax_brackets')
                ->whereIn('legal_tax_id', LegalTax::query()
                    ->whereIn('country_id', [$senegal->id, $ivoryCoast->id])
                    ->whereIn('type', ['progressive'])
                    ->select('id'))
                ->count(),
        );
    }
}
