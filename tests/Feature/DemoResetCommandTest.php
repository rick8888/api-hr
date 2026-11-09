<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DemoResetCommandTest extends TestCase
{
    public function test_demo_reset_refuses_to_run_outside_local_or_testing(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->artisan('demo:reset', ['--force' => true])
            ->expectsOutputToContain('autorisée uniquement en local ou en test')
            ->assertExitCode(1);
    }

    public function test_demo_reset_can_be_cancelled_before_deleting_data(): void
    {
        $this->artisan('demo:reset')
            ->expectsConfirmation('Continuer et recharger les données de démonstration ?', 'no')
            ->expectsOutputToContain('Réinitialisation annulée.')
            ->assertExitCode(0);
    }
}
