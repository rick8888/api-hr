<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Services\TenantContactUserService;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    private const TENANT_PASSWORD = 'password123';

    public function run(): void
    {
        DemoEnvironmentGuard::ensureAllowed(self::class);

        $france = Country::query()->where('code', 'FR')->first();
        $morocco = Country::query()->where('code', 'MA')->first();
        $civ = Country::query()->where('code', 'CIV')->first();

        $companies = [
            [
                'code' => 'BRAVO',
                'country' => $morocco,
                'name' => 'Bravo RH Demo',
                'plan' => 'Enterprise',
                'currency' => 'MAD',
                'email' => 'tenant@bravorh.com',
            ],
            [
                'code' => 'TECHNOVA',
                'country' => $france,
                'name' => 'TechNova SARL',
                'plan' => 'Starter',
                'currency' => 'EUR',
                'email' => 'hr@technova.io',
                'is_active' => false,
            ],
            [
                'code' => 'GREENLOG',
                'country' => $civ,
                'name' => 'GreenLogistics',
                'plan' => 'Business',
                'currency' => 'XOF',
                'email' => 'ops@greenlogistics.com',
            ],
        ];

        foreach ($companies as $data) {
            if ($data['country'] === null) {
                continue;
            }

            Company::query()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'country_id' => $data['country']->id,
                    'name' => $data['name'],
                    'legal_name' => $data['name'],
                    'email' => $data['email'],
                    'currency' => $data['currency'],
                    'is_active' => $data['is_active'] ?? true,
                    'settings' => [
                        'subdomain' => strtolower($data['code']),
                        'legal_form' => 'SARL',
                        'industry' => 'Services',
                        'language' => 'fr',
                        'plan' => $data['plan'],
                        'primary_contact' => [
                            'title' => 'M.',
                            'first_name' => 'Contact',
                            'last_name' => $data['code'],
                            'job_title' => 'DRH',
                            'email' => $data['email'],
                            'phone' => '+33 1 00 00 00 00',
                            'language' => 'fr',
                            'role' => 'tenant',
                        ],
                    ],
                ]
            );
        }

        /** @var TenantContactUserService $tenantUsers */
        $tenantUsers = app(TenantContactUserService::class);
        Company::query()->each(
            fn (Company $company) => $tenantUsers->syncForCompany($company, self::TENANT_PASSWORD)
        );
    }
}
