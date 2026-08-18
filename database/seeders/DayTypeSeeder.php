<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\DayType;
use Illuminate\Database\Seeder;

class DayTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Jour ouvré',
                'code' => 'weekday',
                'description' => 'Jours de la semaine hors week-end et jours fériés.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Week-end',
                'code' => 'weekend',
                'description' => 'Samedi et dimanche.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Jour férié',
                'code' => 'holiday',
                'description' => 'Jours fériés du calendrier entreprise.',
                'sort_order' => 30,
            ],
        ];

        foreach ($types as $type) {
            DayType::query()->updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'sort_order' => $type['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
