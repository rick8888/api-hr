<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\TerminationType;
use Illuminate\Database\Seeder;

class TerminationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Démission',
                'code' => 'resignation',
                'description' => 'Départ à l’initiative du salarié.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Licenciement',
                'code' => 'termination',
                'description' => 'Rupture du contrat à l’initiative de l’employeur.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Fin de contrat',
                'code' => 'contract_end',
                'description' => 'Arrivée à terme d’un CDD ou d’une mission.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Licenciement économique',
                'code' => 'redundancy',
                'description' => 'Rupture pour motif économique.',
                'sort_order' => 40,
            ],
            [
                'name' => 'Retraite',
                'code' => 'retirement',
                'description' => 'Départ à la retraite.',
                'sort_order' => 50,
            ],
            [
                'name' => 'Autre',
                'code' => 'other',
                'description' => 'Autre motif de fin de contrat.',
                'sort_order' => 60,
            ],
        ];

        foreach ($types as $type) {
            TerminationType::query()->updateOrCreate(
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
