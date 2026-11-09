<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_probation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->date('review_date');
            $table->string('decision', 24)->default('check_in');
            $table->date('period_end_before');
            $table->date('extended_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'review_date']);
            $table->index(['employee_id', 'created_at']);
        });

        Schema::create('employee_probation_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_end_date');
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(
                ['employee_id', 'recipient_user_id', 'period_end_date', 'days_before'],
                'probation_reminder_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_probation_reminders');
        Schema::dropIfExists('employee_probation_reviews');
    }
};
