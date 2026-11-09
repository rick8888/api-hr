<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_objectives', function (Blueprint $table) {
            $table->foreignId('training_enrollment_id')
                ->nullable()
                ->after('performance_review_id')
                ->constrained('training_enrollments')
                ->nullOnDelete();

            $table->index('training_enrollment_id', 'performance_objectives_training_enrollment_index');
        });
    }

    public function down(): void
    {
        Schema::table('performance_objectives', function (Blueprint $table) {
            $table->dropForeign(['training_enrollment_id']);
            $table->dropIndex('performance_objectives_training_enrollment_index');
            $table->dropColumn('training_enrollment_id');
        });
    }
};
