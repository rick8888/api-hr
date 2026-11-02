<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class ProvisionalPayrollProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CountrySeeder::class);
        (new LegalTaxSeeder())->seedCountries(['CIV', 'SN', 'CG']);
    }
}
