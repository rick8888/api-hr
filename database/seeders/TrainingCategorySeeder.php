<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\TrainingCategory;
use Illuminate\Database\Seeder;

class TrainingCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Technique',
                'code' => 'technical',
                'description' => 'Formations techniques et métiers.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Management',
                'code' => 'management',
                'description' => 'Formations management et leadership.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Compétences comportementales',
                'code' => 'soft_skills',
                'description' => 'Soft skills et compétences comportementales.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Ventes',
                'code' => 'sales',
                'description' => 'Formations commerciales et ventes.',
                'sort_order' => 40,
            ],
            [
                'name' => 'Conformité',
                'code' => 'compliance',
                'description' => 'Formations réglementaires et conformité.',
                'sort_order' => 50,
            ],
        ];

        foreach ($categories as $category) {
            TrainingCategory::query()->updateOrCreate(
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
