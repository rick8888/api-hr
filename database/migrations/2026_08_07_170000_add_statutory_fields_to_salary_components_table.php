<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->foreignId('legal_tax_id')
                ->nullable()
                ->after('country_id')
                ->constrained('legal_taxes')
                ->nullOnDelete();

            $table->boolean('is_statutory')
                ->default(false)
                ->after('is_active');

            $table->index(['company_id', 'is_statutory', 'is_active']);
            $table->index(['company_id', 'legal_tax_id']);
        });
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_statutory', 'is_active']);
            $table->dropIndex(['company_id', 'legal_tax_id']);
            $table->dropConstrainedForeignId('legal_tax_id');
            $table->dropColumn('is_statutory');
        });
    }
};
