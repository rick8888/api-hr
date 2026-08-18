<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            // draft | active | archived
            $table->string('status')->default('active');

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status'], 'ess_employee_status_idx');
            $table->index(
                ['employee_id', 'effective_from', 'effective_to'],
                'ess_employee_period_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structures');
    }
};
