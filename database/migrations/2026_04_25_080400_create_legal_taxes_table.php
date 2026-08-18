<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();

            // percent | progressive | fixed | formula
            $table->string('type');

            // BASE | GROSS | TAXABLE | NET | CUSTOM
            $table->string('base_target')->default('TAXABLE');

            $table->decimal('employer_rate', 8, 4)->default(0);
            $table->decimal('employee_rate', 8, 4)->default(0);

            // optional ceiling (plafond)
            $table->decimal('ceiling', 18, 2)->nullable();
            $table->decimal('floor', 18, 2)->nullable();

            $table->text('formula')->nullable();

            $table->boolean('reduces_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sequence')->default(100);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_taxes');
    }
};
