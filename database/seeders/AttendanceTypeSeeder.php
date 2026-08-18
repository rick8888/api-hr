<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Models\AttendanceType;
use Illuminate\Database\Seeder;

class AttendanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Open - No Restrictions',
                'code' => 'open',
                'description' => 'Pointage sans restriction de lieu.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Geo-fenced',
                'code' => 'geo',
                'description' => 'Pointage limité à une zone géographique.',
                'sort_order' => 20,
            ],
            [
                'name' => 'IP-restricted',
                'code' => 'ip',
                'description' => 'Pointage limité à des adresses IP autorisées.',
                'sort_order' => 30,
            ],
            [
                'name' => 'Biometric',
                'code' => 'biometric',
                'description' => 'Pointage via dispositif biométrique.',
                'sort_order' => 40,
            ],
            [
                'name' => 'Static QR',
                'code' => 'static_qr',
                'description' => 'QR code fixe pour le pointage.',
                'sort_order' => 50,
            ],
            [
                'name' => 'Dynamic QR',
                'code' => 'dynamic_qr',
                'description' => 'QR code dynamique renouvelé périodiquement.',
                'sort_order' => 60,
            ],
            [
                'name' => 'QR Code',
                'code' => 'qr_code',
                'description' => 'Pointage par scan QR code.',
                'sort_order' => 70,
            ],
            [
                'name' => 'Fixed',
                'code' => 'fixed',
                'description' => 'Horaires de pointage fixes.',
                'sort_order' => 80,
            ],
            [
                'name' => 'Flexible',
                'code' => 'flexible',
                'description' => 'Horaires de pointage flexibles.',
                'sort_order' => 90,
            ],
        ];

        foreach ($types as $type) {
            AttendanceType::query()->updateOrCreate(
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
