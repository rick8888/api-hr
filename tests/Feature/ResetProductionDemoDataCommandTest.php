<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ResetProductionDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_demo_reset_is_refused_outside_production(): void
    {
        $this->artisan('demo:reset-production')
            ->expectsOutputToContain('réservée à l’environnement production')
            ->assertExitCode(1);
    }

    public function test_production_demo_reset_does_not_require_a_backup_file(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DatabaseSeeder::class)->run();

        $database = (string) config('database.connections.'.config('database.default').'.database');
        $this->artisan('demo:reset-production')
            ->expectsQuestion('Saisissez exactement le nom de la base ciblée pour continuer', $database)
            ->expectsConfirmation('Actualiser les données de démonstration sur cette base de production ?', 'no')
            ->expectsOutputToContain('Opération annulée. Aucune donnée n’a été modifiée.')
            ->assertExitCode(0);
    }

    public function test_existing_unmarked_company_code_blocks_reset_without_modifying_the_company(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DatabaseSeeder::class)->run();

        $country = Country::query()->where('code', 'MA')->firstOrFail();
        Company::query()->create([
            'code' => 'ACME',
            'country_id' => $country->id,
            'name' => 'Entreprise cliente ACME',
            'currency' => 'MAD',
            'settings' => ['demo_fixture' => false],
            'is_active' => true,
        ]);

        $this->artisan('demo:reset-production')
            ->expectsOutputToContain('existe sans marqueur de démo vérifiable')
            ->assertExitCode(1);

        $this->assertDatabaseHas('companies', [
            'code' => 'ACME',
            'name' => 'Entreprise cliente ACME',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'contact@acme-maroc.demo']);
    }

    public function test_exact_legacy_acme_demo_fixture_is_recognized_without_a_marker(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DatabaseSeeder::class)->run();

        $country = Country::query()->where('code', 'MA')->firstOrFail();
        Company::query()->create([
            'code' => 'ACME',
            'country_id' => $country->id,
            'name' => 'Acme Maroc SARL',
            'legal_name' => 'Acme Maroc SARL',
            'tax_id' => 'IF-12345678',
            'registration_number' => 'RC-456789',
            'email' => 'contact@acme-maroc.demo',
            'currency' => 'MAD',
            'settings' => [
                'subdomain' => 'acme-maroc',
                'primary_contact' => ['email' => 'contact@acme-maroc.demo'],
            ],
            'is_active' => true,
        ]);

        $database = (string) config('database.connections.'.config('database.default').'.database');
        $this->artisan('demo:reset-production')
            ->expectsQuestion('Saisissez exactement le nom de la base ciblée pour continuer', $database)
            ->expectsConfirmation('Actualiser les données de démonstration sur cette base de production ?', 'no')
            ->expectsOutputToContain('Opération annulée. Aucune donnée n’a été modifiée.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('companies', [
            'code' => 'ACME',
            'name' => 'Acme Maroc SARL',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'contact@acme-maroc.demo']);
    }

    public function test_legacy_demo_companies_are_recognized_and_can_be_refreshed(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DatabaseSeeder::class)->run();

        $legacyCompanies = [
            [
                'code' => 'BRAVO',
                'country' => 'MA',
                'name' => 'Bravo RH Demo',
                'email' => 'tenant@bravorh.com',
                'currency' => 'MAD',
                'subdomain' => 'bravo',
            ],
            [
                'code' => 'TECHNOVA',
                'country' => 'FR',
                'name' => 'TechNova SARL',
                'email' => 'hr@technova.io',
                'currency' => 'EUR',
                'subdomain' => 'technova',
            ],
            [
                'code' => 'GREENLOG',
                'country' => 'CIV',
                'name' => 'GreenLogistics',
                'email' => 'ops@greenlogistics.com',
                'currency' => 'XOF',
                'subdomain' => 'greenlog',
            ],
        ];

        foreach ($legacyCompanies as $data) {
            $country = Country::query()->where('code', $data['country'])->firstOrFail();
            Company::query()->create([
                'code' => $data['code'],
                'country_id' => $country->id,
                'name' => $data['name'],
                'legal_name' => $data['name'],
                'email' => $data['email'],
                'currency' => $data['currency'],
                'settings' => [
                    'subdomain' => $data['subdomain'],
                    'primary_contact' => ['email' => $data['email']],
                ],
                'is_active' => true,
            ]);
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');
        $this->artisan('demo:reset-production')
            ->expectsQuestion('Saisissez exactement le nom de la base ciblée pour continuer', $database)
            ->expectsConfirmation('Actualiser les données de démonstration sur cette base de production ?', 'no')
            ->expectsOutputToContain('Opération annulée. Aucune donnée n’a été modifiée.')
            ->assertExitCode(0);

        foreach ($legacyCompanies as $data) {
            $this->assertDatabaseHas('companies', [
                'code' => $data['code'],
                'name' => $data['name'],
            ]);
        }
    }

    public function test_reset_can_be_cancelled_before_writing_demo_data(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DatabaseSeeder::class)->run();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        $this->artisan('demo:reset-production')
            ->expectsQuestion('Saisissez exactement le nom de la base ciblée pour continuer', $database)
            ->expectsConfirmation('Actualiser les données de démonstration sur cette base de production ?', 'no')
            ->expectsOutputToContain('Opération annulée. Aucune donnée n’a été modifiée.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('companies', ['code' => 'ACME']);
        $this->assertDatabaseMissing('users', ['email' => 'contact@acme-maroc.demo']);
    }

    public function test_production_reset_sets_password123_only_for_demo_accounts(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DatabaseSeeder::class)->run();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        $this->artisan('demo:reset-production')
            ->expectsQuestion('Saisissez exactement le nom de la base ciblée pour continuer', $database)
            ->expectsConfirmation('Actualiser les données de démonstration sur cette base de production ?', 'yes')
            ->assertExitCode(0);

        $fixtureCompanies = Company::query()
            ->whereIn('code', [
                'ACME',
                'BRAVO',
                'TECHNOVA',
                'GREENLOG',
                'PAYROLL-TEST-CD',
                'PAYROLL-TEST-CG',
            ])
            ->get();

        $this->assertCount(6, $fixtureCompanies);
        $this->assertTrue($fixtureCompanies->every(
            static fn (Company $company): bool => ($company->settings['demo_fixture'] ?? false) === true
                || ($company->settings['test_fixture'] ?? false) === true,
        ));

        $acmeContact = User::query()->where('email', 'contact@acme-maroc.demo')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $acmeContact->password));
        $bravoTenant = User::query()->where('email', 'tenant@bravorh.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $bravoTenant->password));
        $demoAdmin = User::query()->where('email', 'admin@bravorh.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $demoAdmin->password));
        $this->assertDatabaseMissing('users', ['email' => 'superadmin@bravorh.com']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);

        $employeeCount = Employee::query()->count();
        $userCount = User::query()->count();

        $this->artisan('demo:reset-production')
            ->expectsQuestion('Saisissez exactement le nom de la base ciblée pour continuer', $database)
            ->expectsConfirmation('Actualiser les données de démonstration sur cette base de production ?', 'yes')
            ->assertExitCode(0);

        $this->assertSame(6, Company::query()->count());
        $this->assertSame($employeeCount, Employee::query()->count());
        $this->assertSame($userCount, User::query()->count());
        $this->assertTrue(Hash::check(
            'password123',
            User::query()->where('email', 'contact@acme-maroc.demo')->valueOrFail('password'),
        ));
    }
}
