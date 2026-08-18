<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\RegularizationType;
use Illuminate\Database\Seeder;

class RegularizationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Pointage manqué',
                'code' => 'missed_punch',
                'description' => 'Régularisation d’un pointage d’entrée ou de sortie manquant.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Heures supplémentaires',
                'code' => 'overtime',
                'description' => 'Demande de régularisation d’heures supplémentaires.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Correction',
                'code' => 'correction',
                'description' => 'Correction d’horaires de présence déjà enregistrés.',
                'sort_order' => 30,
            ],
        ];

        foreach ($types as $type) {
            RegularizationType::query()->updateOrCreate(
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
