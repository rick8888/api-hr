<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['code' => 'MA',  'name' => 'Maroc',                       'currency' => 'MAD', 'locale' => 'fr_MA'],
            ['code' => 'CD',  'name' => 'République Démocratique du Congo', 'currency' => 'CDF', 'locale' => 'fr_CD'],
            ['code' => 'FR',  'name' => 'France',                      'currency' => 'EUR', 'locale' => 'fr_FR'],
            ['code' => 'CIV', 'name' => "Côte d'Ivoire",               'currency' => 'XOF', 'locale' => 'fr_CI'],
            ['code' => 'SN',  'name' => 'Sénégal',                      'currency' => 'XOF', 'locale' => 'fr_SN'],
            ['code' => 'CG',  'name' => 'République du Congo',          'currency' => 'XAF', 'locale' => 'fr_CG'],
        ];

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(['code' => $country['code']], $country);
        }
    }
}
