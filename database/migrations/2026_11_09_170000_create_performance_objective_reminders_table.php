<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_objective_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_objective_id')
                ->constrained('performance_objectives')
                ->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_date');
            $table->smallInteger('days_offset');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['performance_objective_id', 'recipient_user_id', 'due_date', 'days_offset'],
                'performance_objective_reminder_unique'
            );
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_objective_reminders');
    }
};
