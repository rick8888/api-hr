<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->foreignId('designation_id')
                ->nullable()
                ->after('company_id')
                ->constrained('designations')
                ->nullOnDelete();

            $table->index(['company_id', 'designation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
        });
    }
};
