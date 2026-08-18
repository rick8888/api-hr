<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensatory_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('worked_date');
            $table->decimal('hours_worked', 5, 2)->default(0);
            $table->decimal('comp_off_days', 5, 2)->default(0);
            $table->date('expiry_date');
            $table->string('status', 20)->default('pending');
            $table->string('usage_status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'worked_date']);
            $table->index(['company_id', 'usage_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensatory_offs');
    }
};
