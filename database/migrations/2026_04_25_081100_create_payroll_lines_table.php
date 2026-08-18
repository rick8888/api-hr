<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();

            // component | tax_employer | tax_employee | modifier | summary
            $table->string('kind');

            // morphable source (salary_components, legal_taxes, payroll_modifiers...)
            $table->nullableMorphs('source');

            $table->string('code')->index();
            $table->string('label');

            // earning | deduction | info
            $table->string('type')->default('info');

            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('rate', 10, 4)->default(0);
            $table->decimal('amount', 18, 2)->default(0);

            $table->boolean('is_taxable')->default(false);
            $table->boolean('affects_gross')->default(false);
            $table->boolean('affects_net')->default(false);

            $table->unsignedInteger('sequence')->default(100);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['payroll_id', 'kind']);
            $table->index(['payroll_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
    }
};
