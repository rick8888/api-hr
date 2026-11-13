<?php

declare(strict_types=1);

namespace Database\Seeders;

use RuntimeException;

final class DemoEnvironmentGuard
{
    public static function ensureAllowed(string $seeder): void
    {
        if (
            ! app()->environment(['local', 'testing'])
            && config('app.demo_seed_in_progress') !== true
        ) {
            throw new RuntimeException(
                "Le seeder {$seeder} est réservé aux environnements local et testing.",
            );
        }
    }
}
