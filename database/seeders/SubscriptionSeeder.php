<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Models\SubscriptionService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Catalogue services + plans d'abonnement
 * Source : database/data/subscription-services.json & subscription-plans.json
 */
class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServices();
        $this->seedPlans();
    }

    private function seedServices(): void
    {
        $path = database_path('data/subscription-services.json');

        if (! is_readable($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            SubscriptionService::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'label' => $row['label'],
                    'category' => $row['category'] ?? 'core',
                    'description' => $row['description'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedPlans(): void
    {
        $path = database_path('data/subscription-plans.json');

        if (! is_readable($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'tagline' => $row['tagline'] ?? null,
                    'price_monthly' => $row['price_monthly'] ?? 0,
                    'currency' => $row['currency'] ?? 'EUR',
                    'max_users' => $row['max_users'] ?? null,
                    'max_employees' => $row['max_employees'] ?? null,
                    'storage_gb' => $row['storage_gb'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'is_highlighted' => (bool) ($row['is_highlighted'] ?? false),
                    'is_free' => (bool) ($row['is_free'] ?? false),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'included_services' => $row['included_services'] ?? [],
                ]
            );
        }
    }
}
