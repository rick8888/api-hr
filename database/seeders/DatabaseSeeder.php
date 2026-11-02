<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            LegalTaxSeeder::class,
            SubscriptionSeeder::class,
            PaymentMethodSeeder::class,
            ComplianceSeeder::class,
            DemoSeeder::class,
            CompanySeeder::class,
            AuthSeeder::class,
            RoleSeeder::class,
            AttendanceTypeSeeder::class,
            RegularizationTypeSeeder::class,
            DayTypeSeeder::class,
            ExpenseTypeSeeder::class,
            TerminationTypeSeeder::class,
            DocumentCategorySeeder::class,
            DocumentTemplateSeeder::class,
            TrainingCategorySeeder::class,
            EquipmentCategorySeeder::class,
            PayrollCycleSeeder::class,
            EntityStatusSeeder::class,
            BillingSeeder::class,
            CompanySubscriptionSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                PayrollCountryTestDataSeeder::class,
                DemoHrDataSeeder::class,
            ]);
        }
    }
}
