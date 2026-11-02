<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('entity_statuses')
            ->where('entity_type', 'employees')
            ->where('code', 'suspended')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('entity_statuses')->insert([
            'entity_type' => 'employees',
            'code' => 'suspended',
            'name' => 'Suspendu',
            'name_en' => 'Suspended',
            'description' => 'Accès temporairement restreint.',
            'tone' => 'warning',
            'sort_order' => 25,
            'is_system' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('entity_statuses')
            ->where('entity_type', 'employees')
            ->where('code', 'suspended')
            ->where('is_system', true)
            ->delete();
    }
};
