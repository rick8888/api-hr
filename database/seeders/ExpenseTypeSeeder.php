<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\ExpenseType;
use Illuminate\Database\Seeder;

class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Restauration',
                'code' => 'food',
                'description' => 'Repas et frais de restauration.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Déplacement',
                'code' => 'travel',
                'description' => 'Transports et frais de voyage.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Hébergement',
                'code' => 'accommodation',
                'description' => 'Hôtels et nuitées.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Fournitures de bureau',
                'code' => 'office_supplies',
                'description' => 'Matériel et fournitures de bureau.',
                'sort_order' => 40,
            ],
            [
                'name' => 'Formation',
                'code' => 'training',
                'description' => 'Formations, séminaires et certifications.',
                'sort_order' => 50,
            ],
            [
                'name' => 'Autre',
                'code' => 'other',
                'description' => 'Autres dépenses non classées.',
                'sort_order' => 60,
            ],
        ];

        foreach ($types as $type) {
            ExpenseType::query()->updateOrCreate(
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
