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
 * Provisional reference:
 *   - CNSS RDC : employer 13.0% / employee 5.0% on gross salary
 *   - INPP     : employer 1.0% to 3.0%; 2.0% only applies to private firms with 51-300 employees
 *   - ONEM     : employer 0.2% on gross
 *   - IPR (Impôt Professionnel sur le Rémunération): progressive monthly bracket
 *        0       - 1 944 000 CDF  : 3%
 *        1 944 001 - 21 600 000   : 15%
 *        21 600 001 - 43 200 000  : 30%
 *        > 43 200 000             : 40%
 *      (monthly thresholds below; IPR is capped at 30% of taxable salary)
 *
 * Sources:
 *   https://taxsummaries.pwc.com/democratic-republic-of-the-congo/individual/other-taxes
 *   https://taxsummaries.pwc.com/democratic-republic-of-the-congo/individual/taxes-on-personal-income
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
                'description' => "[À VALIDER — activation provisoire demandée] Taux combiné : 13 % employeur (6,5 % prestations familiales, 5 % pension, 1,5 % risques professionnels) et 5 % salarié pour la pension. Confirmer l'assiette et l'actualité avant usage en production.",
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
                'description' => "[À VALIDER — activation provisoire demandée] Le taux de 2 % ne vaut que pour les entreprises privées de 51 à 300 salariés ; 3 % jusqu'à 50 salariés et 1 % au-delà. Le profil ne varie pas encore selon l'effectif.",
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
                'description' => "[À VALIDER — activation provisoire demandée] Contribution employeur de 0,2 %. Confirmer l'assiette et l'actualité avant usage en production.",
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
                'description' => "[À VALIDER — activation provisoire demandée] Barème mensuel avec plafonnement provisoire du montant calculé à 30 % de la base imposable. Confirmer le barème, le calcul mensuel et les règles d'assiette avant usage en production.",
                'type' => LegalTax::TYPE_PROGRESSIVE,
                'base_target' => LegalTax::BASE_TAXABLE,
                'formula' => 'MIN(TAX_AMOUNT, BASE * 0.3)',
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
