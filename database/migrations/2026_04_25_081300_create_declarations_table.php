<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('compliance_obligation_id')->constrained()->cascadeOnDelete();

            $table->string('period', 7);

            // draft | generated | submitted | accepted | rejected
            $table->string('status')->default('draft');

            $table->string('file_path')->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declarations');
    }
};
