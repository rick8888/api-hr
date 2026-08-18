<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->string('matricule')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();

            $table->string('national_id')->nullable();
            $table->string('social_security_number')->nullable();
            $table->string('tax_number')->nullable();

            $table->string('job_title')->nullable();
            $table->string('department')->nullable();

            $table->string('contract_type')->default('CDI');
            $table->string('payment_method')->default('bank');
            $table->string('bank_account')->nullable();

            $table->decimal('base_salary', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();

            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_active']);
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
