<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->boolean('proof_required')->default(false);
            $table->boolean('is_compensatory_off_type')->default(false);
            $table->boolean('enable_automatic_accrual')->default(false);
            $table->string('accrual_frequency', 20)->nullable();
            $table->decimal('accrual_rate', 8, 2)->nullable();
            $table->unsignedInteger('max_accrual_limit')->nullable();
            $table->boolean('allow_carry_forward')->default(false);
            $table->unsignedInteger('max_carry_forward_days')->nullable();
            $table->unsignedInteger('carry_forward_expiry_months')->nullable();
            $table->boolean('allow_encashment')->default(false);
            $table->unsignedInteger('max_encashment_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
