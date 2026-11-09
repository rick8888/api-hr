<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\Company\CompanyData;
use App\Application\Services\CompanyService;
use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InactiveCompanyAccessTest extends TestCase
{
    private string $originalDefaultConnection;

    /** @var array<string, mixed> */
    private array $originalSqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
        $this->originalSqliteConnection = config('database.connections.sqlite');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');
        config([
            'database.default' => $this->originalDefaultConnection,
            'database.connections.sqlite' => $this->originalSqliteConnection,
        ]);

        parent::tearDown();
    }

    public function test_deactivating_a_company_revokes_sessions_and_blocks_new_logins_until_reactivated(): void
    {
        $this->seed(RoleSeeder::class);
        [$company, $tenant] = $this->createTenant();
        $staff = User::query()->create([
            'name' => 'Company Admin',
            'email' => 'company-admin@example.test',
            'password' => 'secret-password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $tenantToken = $tenant->createToken('tenant-session')->plainTextToken;
        $staffToken = $staff->createToken('staff-session')->plainTextToken;

        app(CompanyService::class)->toggleStatus($company, false);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $tenant->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $staff->id,
        ]);
        $this->withToken($staffToken)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->withToken($tenantToken)->getJson('/api/v1/tenant/subscription')->assertOk();
        $this->postJson('/api/v1/auth/login', [
            'email' => $tenant->email,
            'password' => 'secret-password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'secret-password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'L’accès à votre entreprise est suspendu. Contactez le support.');

        app(CompanyService::class)->toggleStatus($company->fresh(), true);

        $this->postJson('/api/v1/auth/login', [
            'email' => $staff->email,
            'password' => 'secret-password',
        ])->assertOk();
    }

    public function test_company_currency_defaults_to_country_currency_or_configured_default(): void
    {
        config(['currency.default' => 'USD']);
        $service = app(CompanyService::class);
        $morocco = Country::query()->create([
            'code' => 'MAR',
            'name' => 'Morocco',
            'currency' => 'MAD',
        ]);
        $unitedStates = Country::query()->create([
            'code' => 'USA',
            'name' => 'United States',
            'currency' => 'USD',
        ]);

        $moroccanCompany = $service->create(CompanyData::fromArray([
            'country_id' => $morocco->id,
            'name' => 'Moroccan Company',
            'code' => 'MOROCCO-CURRENCY',
        ]));
        $usCompany = $service->create(CompanyData::fromArray([
            'country_id' => $unitedStates->id,
            'name' => 'US Company',
            'code' => 'US-CURRENCY',
        ]));

        $this->assertSame('MAD', $moroccanCompany->currency);
        $this->assertSame('USD', $usCompany->currency);
    }

    /** @return array{Company, User} */
    private function createTenant(): array
    {
        $country = Country::query()->create([
            'code' => 'TST',
            'name' => 'Testland',
            'currency' => 'EUR',
        ]);
        $company = Company::query()->create([
            'country_id' => $country->id,
            'code' => 'INACTIVE-TEST',
            'name' => 'Inactive Company Test',
            'currency' => 'EUR',
            'settings' => ['plan' => 'starter'],
            'is_active' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Tenant User',
            'email' => 'tenant-inactive@example.test',
            'password' => 'secret-password',
            'role' => 'tenant',
            'company_id' => $company->id,
        ]);

        return [$company, $user];
    }
}
