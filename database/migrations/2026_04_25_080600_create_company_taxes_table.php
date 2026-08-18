<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_tax_id')->constrained()->cascadeOnDelete();

            $table->decimal('override_employer_rate', 8, 4)->nullable();
            $table->decimal('override_employee_rate', 8, 4)->nullable();
            $table->decimal('override_ceiling', 18, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'legal_tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_taxes');
    }
};
