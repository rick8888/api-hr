<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_modifiers', function (Blueprint $table) {
            $table->id();

            // global = applies to all employees of a company / country
            // employee = applies to one employee only
            $table->string('scope')->default('global');

            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();

            // BASE | GROSS | TAXABLE | NET
            $table->string('target')->default('NET');

            // fixed | percent | formula
            $table->string('type')->default('fixed');

            $table->decimal('value', 18, 6)->default(0);
            $table->text('formula')->nullable();

            // + or -
            $table->string('sign', 1)->default('+');

            // Optional period restriction (YYYY-MM)
            $table->string('period_from', 7)->nullable();
            $table->string('period_to', 7)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sequence')->default(100);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_modifiers');
    }
};
