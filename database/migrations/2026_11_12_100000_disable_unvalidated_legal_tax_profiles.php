<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal_taxes')
            ->where('description', 'like', '[À VALIDER%')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        DB::table('legal_taxes')
            ->where('code', 'IR')
            ->whereIn('country_id', DB::table('countries')->select('id')->where('code', 'MA'))
            ->where('description', 'like', '%annual brackets / 12%')
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Do not reactivate unverified payroll rules when rolling back.
    }
};
