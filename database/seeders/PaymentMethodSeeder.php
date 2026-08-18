<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/payment-methods.json');

        if (! File::exists($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'label' => $row['label'],
                    'description' => $row['description'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ]
            );
        }
    }
}
