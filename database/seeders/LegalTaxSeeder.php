<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Country;
use App\Domain\Tax\Models\LegalTax;
use App\Domain\Tax\Models\TaxBracket;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Cotisations légales (LegalTax) — données alignées sur front-bravo/src/data/legal-tax/legal-taxes.json
 *
 * Source : database/data/legal-taxes.json
 * Pays : MA (Maroc), CD (RDC), CG (Congo), CIV (Côte d'Ivoire), SN (Sénégal)
 *
 * Les profils CIV/SN marqués « à valider » sont activés provisoirement ; leurs
 * règles doivent être confirmées avant tout usage en production.
 */
class LegalTaxSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCountries([]);
    }

    /**
     * @param list<string> $countryCodes Empty means seed every country in the source file.
     */
    public function seedCountries(array $countryCodes): void
    {
        $path = database_path('data/legal-taxes.json');

        if (! is_readable($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            $countryCode = (string) ($row['country_code'] ?? '');
            if ($countryCodes !== [] && ! in_array($countryCode, $countryCodes, true)) {
                continue;
            }

            $country = Country::query()->where('code', $countryCode)->first();

            if ($country === null) {
                $this->command?->warn("Pays ignoré (introuvable) : {$countryCode}");
                continue;
            }

            $brackets = $row['brackets'] ?? [];
            unset($row['country_code'], $row['brackets']);

            $tax = LegalTax::query()->updateOrCreate(
                ['country_id' => $country->id, 'code' => $row['code']],
                [
                    'country_id' => $country->id,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'category' => $row['category'] ?? LegalTax::CATEGORY_SOCIAL,
                    'type' => $row['type'],
                    'base_target' => $row['base_target'],
                    'employer_rate' => $row['employer_rate'] ?? 0,
                    'employee_rate' => $row['employee_rate'] ?? 0,
                    'ceiling' => $row['ceiling'] ?? null,
                    'floor' => $row['floor'] ?? null,
                    'formula' => $row['formula'] ?? null,
                    'reduces_taxable' => (bool) ($row['reduces_taxable'] ?? false),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'sequence' => (int) ($row['sequence'] ?? 100),
                    'effective_from' => $row['effective_from'] ?? null,
                    'effective_to' => $row['effective_to'] ?? null,
                ]
            );

            TaxBracket::query()->where('legal_tax_id', $tax->id)->delete();

            foreach ($brackets as $i => $bracket) {
                $tax->brackets()->create([
                    'min_amount' => $bracket['min_amount'],
                    'max_amount' => $bracket['max_amount'] ?? null,
                    'rate' => $bracket['rate'],
                    'fixed_deduction' => $bracket['fixed_deduction'] ?? 0,
                    'sequence' => $bracket['sequence'] ?? $i,
                ]);
            }
        }
    }
}
