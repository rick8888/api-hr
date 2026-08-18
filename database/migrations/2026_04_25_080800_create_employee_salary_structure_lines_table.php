<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_structure_id')
                ->constrained('employee_salary_structures', 'id', 'esl_structure_fk')
                ->cascadeOnDelete();
            $table->foreignId('salary_component_id')
                ->constrained('salary_components', 'id', 'esl_component_fk')
                ->cascadeOnDelete();

            // optional override of the component default
            $table->decimal('amount_override', 18, 6)->nullable();
            $table->text('formula_override')->nullable();
            $table->decimal('rate_override', 8, 4)->nullable();

            $table->unsignedInteger('sequence')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['employee_salary_structure_id', 'salary_component_id'],
                'esl_struct_component_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structure_lines');
    }
};
