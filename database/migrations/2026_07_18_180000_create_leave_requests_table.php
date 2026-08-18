<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type', 40);
            $table->string('duration_kind', 10)->default('full');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1)->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->boolean('emergency_contact')->default(false);
            $table->boolean('traveling_abroad')->default(false);
            $table->string('document')->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'start_date', 'end_date']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'leave_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
