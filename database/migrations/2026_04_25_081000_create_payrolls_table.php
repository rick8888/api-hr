<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            // YYYY-MM
            $table->string('period', 7)->index();
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('gross', 18, 2)->default(0);
            $table->decimal('taxable', 18, 2)->default(0);
            $table->decimal('net', 18, 2)->default(0);

            $table->decimal('total_employer_charges', 18, 2)->default(0);
            $table->decimal('total_employee_charges', 18, 2)->default(0);
            $table->decimal('total_earnings', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);

            $table->string('currency', 3)->nullable();

            // draft | computed | validated | paid | cancelled
            $table->string('status')->default('computed');

            $table->json('summary')->nullable();
            $table->json('snapshot')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'period']);
            $table->index(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
