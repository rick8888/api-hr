<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('review_date');
            $table->unsignedTinyInteger('rating');
            $table->text('strengths')->nullable();
            $table->text('improvement_areas')->nullable();
            $table->text('comments')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_start', 'period_end']);
            $table->index(['company_id', 'status', 'review_date']);
        });

        Schema::create('performance_objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')
                ->constrained('performance_reviews')
                ->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();

            $table->index(['performance_review_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_objectives');
        Schema::dropIfExists('performance_reviews');
    }
};
