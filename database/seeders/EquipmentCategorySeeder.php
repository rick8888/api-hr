<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\EquipmentCategory;
use Illuminate\Database\Seeder;

class EquipmentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Ordinateurs portables',
                'code' => 'laptop',
                'description' => 'Ordinateurs portables.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Ordinateurs de bureau',
                'code' => 'desktop',
                'description' => 'Ordinateurs de bureau.',
                'sort_order' => 20,
            ],
            [
                'name' => 'Écrans',
                'code' => 'monitor',
                'description' => 'Écrans et moniteurs.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Téléphones',
                'code' => 'phone',
                'description' => 'Téléphones professionnels.',
                'sort_order' => 40,
            ],
            [
                'name' => 'Tablettes',
                'code' => 'tablet',
                'description' => 'Tablettes.',
                'sort_order' => 50,
            ],
            [
                'name' => 'Accessoires',
                'code' => 'accessories',
                'description' => 'Accessoires informatiques et bureautiques.',
                'sort_order' => 60,
            ],
            [
                'name' => 'Mobilier',
                'code' => 'furniture',
                'description' => 'Mobilier de bureau.',
                'sort_order' => 70,
            ],
            [
                'name' => 'Autre',
                'code' => 'other',
                'description' => 'Autres matériels non classés.',
                'sort_order' => 80,
            ],
        ];

        foreach ($categories as $category) {
            EquipmentCategory::query()->updateOrCreate(
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
