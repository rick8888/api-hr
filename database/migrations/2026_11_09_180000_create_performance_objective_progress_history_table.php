<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_objective_progress_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_objective_id')
                ->index('objective_progress_history_objective_index');
            $table->foreign('performance_objective_id', 'objective_progress_history_objective_fk')
                ->references('id')
                ->on('performance_objectives')
                ->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 20);
            $table->unsignedTinyInteger('previous_progress');
            $table->string('status', 20);
            $table->unsignedTinyInteger('progress');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['performance_objective_id', 'created_at'], 'objective_progress_history_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_objective_progress_history');
    }
};
