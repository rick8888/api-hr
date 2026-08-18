<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\PayrollCycle;
use Illuminate\Database\Seeder;

class PayrollCycleSeeder extends Seeder
{
    public function run(): void
    {
        $cycles = [
            [
                'name' => 'Mensuel',
                'code' => 'monthly',
                'description' => 'Cycle de paie mensuel.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Bi-mensuel',
                'code' => 'biweekly',
                'description' => 'Cycle de paie bi-mensuel.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Hebdomadaire',
                'code' => 'weekly',
                'description' => 'Cycle de paie hebdomadaire.',
                'sort_order' => 30,
            ],
        ];

        foreach ($cycles as $cycle) {
            PayrollCycle::query()->updateOrCreate(
                ['code' => $cycle['code']],
                [
                    'name' => $cycle['name'],
                    'description' => $cycle['description'],
                    'sort_order' => $cycle['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
