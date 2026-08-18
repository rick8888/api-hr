<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Country;
use App\Domain\Tax\Models\LegalTax;
use App\Domain\Tax\Models\TaxBracket;
use Illuminate\Database\Seeder;

/**
 * Seeds DR Congo (RDC) legal taxes.
 *
 * Reference (illustrative):
 *   - CNSS RDC : employer 13.0% / employee 5.0% on gross salary
 *   - INPP     : employer 1.0% to 3.0% (we use 2.0%) on gross
 *   - ONEM     : employer 0.2% on gross
 *   - IPR (Impôt Professionnel sur le Rémunération): progressive monthly bracket
 *        0       - 1 944 000 CDF  : 3%
 *        1 944 001 - 21 600 000   : 15%
 *        21 600 001 - 43 200 000  : 30%
 *        > 43 200 000             : 40%
 *      (annualised values; in production, divide by 12 if applied monthly)
 */
class DrcTaxSeeder extends Seeder
{
    public function run(): void
    {
        $rdc = Country::query()->where('code', 'CD')->firstOrFail();

        LegalTax::query()->updateOrCreate(
            ['country_id' => $rdc->id, 'code' => 'CNSS_RDC'],
            [
                'name' => 'CNSS RDC',
                'description' => "Cotisation sécurité sociale (RDC).",
                'type' => LegalTax::TYPE_PERCENT,
                'base_target' => LegalTax::BASE_GROSS,
                'employer_rate' => 0.130,
                'employee_rate' => 0.050,
                'ceiling' => null,
                'reduces_taxable' => true,
                'is_active' => true,
                'sequence' => 10,
            ]
        );

        LegalTax::query()->updateOrCreate(
            ['country_id' => $rdc->id, 'code' => 'INPP'],
            [
                'name' => 'INPP',
                'description' => "Institut National de Préparation Professionnelle.",
                'type' => LegalTax::TYPE_PERCENT,
                'base_target' => LegalTax::BASE_GROSS,
                'employer_rate' => 0.020,
                'employee_rate' => 0.000,
                'ceiling' => null,
                'reduces_taxable' => false,
                'is_active' => true,
                'sequence' => 20,
            ]
        );

        LegalTax::query()->updateOrCreate(
            ['country_id' => $rdc->id, 'code' => 'ONEM'],
            [
                'name' => 'ONEM',
                'description' => "Office National de l'Emploi.",
                'type' => LegalTax::TYPE_PERCENT,
                'base_target' => LegalTax::BASE_GROSS,
                'employer_rate' => 0.002,
                'employee_rate' => 0.000,
                'ceiling' => null,
                'reduces_taxable' => false,
                'is_active' => true,
                'sequence' => 30,
            ]
        );

        $ipr = LegalTax::query()->updateOrCreate(
            ['country_id' => $rdc->id, 'code' => 'IPR'],
            [
                'name' => 'IPR',
                'description' => "Impôt Professionnel sur le Revenu (barème mensuel).",
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

        TaxBracket::query()->where('legal_tax_id', $ipr->id)->delete();

        // Pure progressive (per-slice) brackets — no fixed_deduction.
        $brackets = [
            ['min' => 0,         'max' => 162000,    'rate' => 0.03],
            ['min' => 162001,    'max' => 1800000,   'rate' => 0.15],
            ['min' => 1800001,   'max' => 3600000,   'rate' => 0.30],
            ['min' => 3600001,   'max' => null,      'rate' => 0.40],
        ];

        foreach ($brackets as $i => $b) {
            $ipr->brackets()->create([
                'min_amount' => $b['min'],
                'max_amount' => $b['max'],
                'rate' => $b['rate'],
                'fixed_deduction' => 0,
                'sequence' => $i,
            ]);
        }
    }
}
