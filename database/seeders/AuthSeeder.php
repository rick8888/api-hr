<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        DemoEnvironmentGuard::ensureAllowed(self::class);

        $demoPassword = bcrypt('password123');

        $bravo = Company::query()->where('code', 'BRAVO')->first();
        $acme = Company::query()->where('code', 'ACME')->first();

        $users = [
            [
                'email' => 'superadmin@bravorh.com',
                'name' => 'Sarah Mitchell',
                'role' => 'super_admin',
                'company_id' => null,
            ],
            [
                'email' => 'admin@bravorh.com',
                'name' => 'Robert Anderson',
                'role' => 'admin',
                'company_id' => $acme?->id,
            ],
            [
                'email' => 'tenant@bravorh.com',
                'name' => 'Jordan Lee',
                'role' => 'tenant',
                'company_id' => $bravo?->id,
            ],
            [
                'email' => 'employee@bravorh.com',
                'name' => 'Brooklyn Simmons',
                'role' => 'employe',
                'company_id' => $acme?->id,
            ],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'role' => $data['role'],
                    'company_id' => $data['company_id'],
                    'password' => $demoPassword,
                ]
            );
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'role' => 'super_admin',
                'password' => bcrypt('password'),
            ]
        );
    }
}
