<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled', 8, 2)->default(0);
            $table->decimal('carried_forward', 8, 2)->default(0);
            $table->decimal('additional', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['company_id', 'employee_id', 'leave_type_id', 'year'],
                'leave_balances_company_employee_type_year_unique'
            );
            $table->index(['company_id', 'year']);
        });

        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('type', 30);
            $table->decimal('days', 8, 2);
            $table->decimal('balance_before', 8, 2);
            $table->decimal('balance_after', 8, 2);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'year'], 'leave_adjustments_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_adjustments');
        Schema::dropIfExists('leave_balances');
    }
};
