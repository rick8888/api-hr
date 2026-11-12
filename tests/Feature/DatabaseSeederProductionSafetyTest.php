<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoEmployeeOffboardingSeeder;
use Database\Seeders\DemoHrDataSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoWorkflowDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_create_demo_companies_or_accounts_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseMissing('companies', ['code' => 'ACME']);
        $this->assertDatabaseMissing('companies', ['code' => 'BRAVO']);
        $this->assertDatabaseMissing('users', ['email' => 'superadmin@bravorh.com']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
        $this->assertSame(0, Company::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_demo_seeders_refuse_direct_execution_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        $seeders = [
            DemoSeeder::class,
            CompanySeeder::class,
            AuthSeeder::class,
            DemoHrDataSeeder::class,
            DemoWorkflowDataSeeder::class,
            DemoEmployeeOffboardingSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            try {
                app($seeder)->run();
                $this->fail("Expected {$seeder} to be blocked in production.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('réservé aux environnements', $exception->getMessage());
            }
        }
    }
}
