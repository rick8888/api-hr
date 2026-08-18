<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();

            // earning | deduction
            $table->string('type');

            // fixed | percent | formula
            $table->string('calculation_type')->default('fixed');

            $table->decimal('default_value', 18, 6)->default(0);
            $table->text('formula')->nullable();

            // For percent calculations: which base to apply on (BASE, GROSS, etc.)
            $table->string('base_target')->nullable();

            $table->boolean('is_taxable')->default(true);
            $table->boolean('affects_gross')->default(true);
            $table->boolean('affects_net')->default(true);
            $table->boolean('is_active')->default(true);

            // execution order (lower runs earlier)
            $table->unsignedInteger('sequence')->default(100);

            // dependencies on other components (codes, JSON array)
            $table->json('depends_on')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
