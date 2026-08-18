<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Passeport',
                'code' => 'passport',
                'description' => 'Passeport de l’employé.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Visa de travail',
                'code' => 'work_visa',
                'description' => 'Visa ou titre de séjour professionnel.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Contrat de travail',
                'code' => 'employment_contract',
                'description' => 'Contrat de travail signé.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Permis de conduire',
                'code' => 'driving_license',
                'description' => 'Permis de conduire.',
                'sort_order' => 40,
            ],
            [
                'name' => 'Pièce d’identité',
                'code' => 'national_id',
                'description' => 'Carte nationale d’identité ou équivalent.',
                'sort_order' => 50,
            ],
            [
                'name' => 'Autre',
                'code' => 'other',
                'description' => 'Autres documents non classés.',
                'sort_order' => 60,
            ],
        ];

        foreach ($categories as $category) {
            DocumentCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'sort_order' => $category['sort_order'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
