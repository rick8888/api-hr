<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('demo:reset {--force : Exécuter sans demander confirmation}', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('La réinitialisation des données de démonstration est autorisée uniquement en local ou en test.');

        return 1;
    }

    $connection = config('database.default');
    $database = config("database.connections.{$connection}.database");
    $this->warn("Base ciblée : {$connection} / {$database}");
    $this->warn('Toutes les tables et les données de cette base seront supprimées.');

    if (! $this->option('force') && ! $this->confirm('Continuer et recharger les données de démonstration ?')) {
        $this->info('Réinitialisation annulée.');

        return 0;
    }

    return $this->call('migrate:fresh', [
        '--seed' => true,
        '--seeder' => DatabaseSeeder::class,
        '--force' => true,
    ]);
})->purpose('Réinitialise la base locale et recharge les données de démonstration');

Schedule::command('hr:generate-payrolls')->dailyAt('02:00');
Schedule::command('hr:send-probation-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('hr:send-objective-reminders')->dailyAt('08:15')->withoutOverlapping();
