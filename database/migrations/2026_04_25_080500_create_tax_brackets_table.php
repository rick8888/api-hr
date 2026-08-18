<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_tax_id')->constrained()->cascadeOnDelete();

            $table->decimal('min_amount', 18, 2);
            $table->decimal('max_amount', 18, 2)->nullable(); // null = infini
            $table->decimal('rate', 8, 4)->default(0);
            $table->decimal('fixed_deduction', 18, 2)->default(0);

            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['legal_tax_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
