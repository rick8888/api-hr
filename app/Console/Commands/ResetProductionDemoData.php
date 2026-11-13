<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Country;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Database\Seeders\AuthSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\DemoEmployeeOffboardingSeeder;
use Database\Seeders\DemoHrDataSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoWorkflowDataSeeder;
use Database\Seeders\PayrollCountryTestDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ResetProductionDemoData extends Command
{
    protected $signature = 'demo:reset-production';

    protected $description = 'Actualise les données de démonstration en production sans effacer les données clientes';

    private const FIXTURE_COMPANY_CODES = [
        'ACME',
        'BRAVO',
        'TECHNOVA',
        'GREENLOG',
        'PAYROLL-TEST-CD',
        'PAYROLL-TEST-CG',
    ];

    private const DEMO_COMPANY_CODES = [
        'ACME',
        'BRAVO',
        'TECHNOVA',
        'GREENLOG',
    ];

    private const TEST_COMPANY_CODES = [
        'PAYROLL-TEST-CD',
        'PAYROLL-TEST-CG',
    ];

    private const FIXTURE_USER_EMAILS = [
        'contact@acme-maroc.demo',
        'tenant@bravorh.com',
        'hr@technova.io',
        'ops@greenlogistics.com',
        'admin@bravorh.com',
        'employee@bravorh.com',
    ];

    private const FIXTURE_EMPLOYEE_CODES = [
        'EMP-0001',
        'EMP-0002',
        'EMP-0003',
        'EMP-0004',
        'EMP-0005',
        'EMP-0006',
        'EMP-DEMO-SORTIE-001',
        'PAYROLL-TEST-CD-001',
        'PAYROLL-TEST-CG-001',
    ];

    public function handle(): int
    {
        if (! app()->environment('production')) {
            $this->error('Cette commande est réservée à l’environnement production.');

            return self::FAILURE;
        }

        $connection = (string) config('database.default');
        $database = config("database.connections.{$connection}.database");
        if (! is_string($database) || trim($database) === '') {
            $this->error('Impossible d’identifier la base de données ciblée.');

            return self::FAILURE;
        }

        if (! $this->validateFixtureTargets()) {
            return self::FAILURE;
        }

        $this->warn("Base ciblée : {$connection} / {$database}");
        $this->warn('La commande actualise uniquement les sociétés et comptes de démonstration reconnus.');
        $this->warn('Elle ne supprime aucune table et ne réinitialise pas les données clientes ou les catalogues fiscaux.');

        if ($this->ask('Saisissez exactement le nom de la base ciblée pour continuer') !== $database) {
            $this->error('Le nom de base ne correspond pas. Aucune donnée n’a été modifiée.');

            return self::FAILURE;
        }

        if (! $this->confirm('Actualiser les données de démonstration sur cette base de production ?', false)) {
            $this->info('Opération annulée. Aucune donnée n’a été modifiée.');

            return self::SUCCESS;
        }

        $previousAuthorization = config('app.demo_seed_in_progress');
        $previousDemoPassword = config('app.demo_seed_password');
        config([
            'app.demo_seed_in_progress' => true,
            'app.demo_seed_password' => bin2hex(random_bytes(32)),
        ]);

        try {
            DB::transaction(function (): void {
                foreach ([
                    DemoSeeder::class,
                    CompanySeeder::class,
                    PayrollCountryTestDataSeeder::class,
                    AuthSeeder::class,
                    DemoHrDataSeeder::class,
                    DemoWorkflowDataSeeder::class,
                    DemoEmployeeOffboardingSeeder::class,
                ] as $seeder) {
                    app($seeder)->run();
                    $this->line("Terminé : {$seeder}");
                }
            });
        } finally {
            config([
                'app.demo_seed_in_progress' => $previousAuthorization,
                'app.demo_seed_password' => $previousDemoPassword,
            ]);
        }

        $this->info('Les données de démonstration ont été actualisées avec succès.');

        return self::SUCCESS;
    }

    private function validateFixtureTargets(): bool
    {
        $companies = Company::withTrashed()
            ->whereIn('code', self::FIXTURE_COMPANY_CODES)
            ->get()
            ->keyBy('code');

        foreach ($companies as $company) {
            $settings = is_array($company->settings) ? $company->settings : [];
            $isDemoFixture = in_array($company->code, self::DEMO_COMPANY_CODES, true)
                && ($settings['demo_fixture'] ?? false) === true;
            $isTestFixture = in_array($company->code, self::TEST_COMPANY_CODES, true)
                && ($settings['test_fixture'] ?? false) === true;

            if ($company->trashed() || (! $isDemoFixture && ! $isTestFixture)) {
                $this->error(
                    "La société « {$company->code} » existe sans marqueur de démo vérifiable. "
                    .'Aucune donnée ne sera modifiée ; vérifiez cette société manuellement.',
                );

                return false;
            }
        }

        $companyIds = $companies->pluck('id')->all();
        $foreignUser = User::query()
            ->whereIn('email', self::FIXTURE_USER_EMAILS)
            ->where(function ($query) use ($companyIds): void {
                $query->whereNull('company_id')
                    ->orWhereNotIn('company_id', $companyIds);
            })
            ->first();

        if ($foreignUser !== null) {
            $this->error(
                "Le compte {$foreignUser->email} existe hors des sociétés de démonstration. "
                .'Aucune donnée ne sera modifiée.',
            );

            return false;
        }

        $foreignEmployee = Employee::withTrashed()
            ->whereIn('matricule', self::FIXTURE_EMPLOYEE_CODES)
            ->where(function ($query) use ($companyIds): void {
                $query->whereNull('company_id')
                    ->orWhereNotIn('company_id', $companyIds);
            })
            ->first();

        if ($foreignEmployee !== null) {
            $this->error(
                "Le matricule {$foreignEmployee->matricule} existe hors des sociétés de démonstration. "
                .'Aucune donnée ne sera modifiée.',
            );

            return false;
        }

        if (Employee::withTrashed()
            ->whereIn('matricule', self::FIXTURE_EMPLOYEE_CODES)
            ->whereNotNull('deleted_at')
            ->exists()) {
            $this->error('Un matricule de démonstration est supprimé logiquement. Vérifiez-le manuellement avant de continuer.');

            return false;
        }

        $requiredCountries = ['MA', 'FR', 'CIV', 'CD', 'CG'];
        $availableCountries = Country::query()
            ->whereIn('code', $requiredCountries)
            ->pluck('code')
            ->all();
        $missingCountries = array_diff($requiredCountries, $availableCountries);

        if ($missingCountries !== []) {
            $this->error('Pays de démonstration manquants : '.implode(', ', $missingCountries).'.');

            return false;
        }

        return true;
    }
}
