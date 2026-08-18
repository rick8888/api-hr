<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Country;
use App\Domain\Tax\Models\LegalTax;
use App\Domain\Tax\Models\TaxBracket;
use Illuminate\Database\Seeder;

/**
 * Seeds Moroccan legal taxes.
 *
 * Reference rates (illustrative — keep regulatory verification on the business side):
 *   - CNSS (employee 4.48%, employer 8.60%) capped at 6 000 MAD per month
 *   - CNSS prestations familiales (employer only 6.40%, no cap) -- modeled separately if needed
 *   - AMO (employee 2.26%, employer 1.85%) on full salary
 *   - IR (impôt sur le revenu) progressive bracket 2024:
 *        0      -  30 000   : 0%       fixed deduction 0
 *        30 001 -  50 000   : 10%      fixed deduction 3 000
 *        50 001 -  60 000   : 20%      fixed deduction 8 000
 *        60 001 -  80 000   : 30%      fixed deduction 14 000
 *        80 001 - 180 000   : 34%      fixed deduction 17 200
 *       > 180 000           : 38%      fixed deduction 24 400
 *   IR brackets are ANNUAL → divided by 12 to apply monthly.
 */
class MoroccoTaxSeeder extends Seeder
{
    public function run(): void
    {
        $morocco = Country::query()->where('code', 'MA')->firstOrFail();

        $cnss = LegalTax::query()->updateOrCreate(
            ['country_id' => $morocco->id, 'code' => 'CNSS'],
            [
                'name' => 'CNSS',
                'description' => "Caisse Nationale de Sécurité Sociale (court terme + long terme)",
                'type' => LegalTax::TYPE_PERCENT,
                'base_target' => LegalTax::BASE_GROSS,
                'employer_rate' => 0.0860,
                'employee_rate' => 0.0448,
                'ceiling' => 6000.00,
                'reduces_taxable' => true,
                'is_active' => true,
                'sequence' => 10,
            ]
        );

        $amo = LegalTax::query()->updateOrCreate(
            ['country_id' => $morocco->id, 'code' => 'AMO'],
            [
                'name' => "AMO",
                'description' => "Assurance Maladie Obligatoire",
                'type' => LegalTax::TYPE_PERCENT,
                'base_target' => LegalTax::BASE_GROSS,
                'employer_rate' => 0.0185,
                'employee_rate' => 0.0226,
                'ceiling' => null,
                'reduces_taxable' => true,
                'is_active' => true,
                'sequence' => 20,
            ]
        );

        $ir = LegalTax::query()->updateOrCreate(
            ['country_id' => $morocco->id, 'code' => 'IR'],
            [
                'name' => 'Impôt sur le revenu (IR)',
                'description' => "Barème progressif IR mensualisé (annual brackets / 12).",
                'type' => LegalTax::TYPE_PROGRESSIVE,
                'base_target' => LegalTax::BASE_TAXABLE,
                'employer_rate' => 0,
                'employee_rate' => 0,
                'ceiling' => null,
                'reduces_taxable' => false,
                'is_active' => true,
                'sequence' => 100,
            ]
        );

        // Recreate brackets so re-running the seeder does not duplicate them.
        TaxBracket::query()->where('legal_tax_id', $ir->id)->delete();

        $brackets = [
            ['min' => 0,        'max' => 2500,    'rate' => 0.00, 'fix' => 0],
            ['min' => 2500.01,  'max' => 4166.66, 'rate' => 0.10, 'fix' => 250],
            ['min' => 4166.67,  'max' => 5000,    'rate' => 0.20, 'fix' => 666.67],
            ['min' => 5000.01,  'max' => 6666.66, 'rate' => 0.30, 'fix' => 1166.67],
            ['min' => 6666.67,  'max' => 15000,   'rate' => 0.34, 'fix' => 1433.33],
            ['min' => 15000.01, 'max' => null,    'rate' => 0.38, 'fix' => 2033.33],
        ];

        foreach ($brackets as $i => $b) {
            $ir->brackets()->create([
                'min_amount' => $b['min'],
                'max_amount' => $b['max'],
                'rate' => $b['rate'],
                'fixed_deduction' => $b['fix'],
                'sequence' => $i,
            ]);
        }
    }
}
