<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('category', 40);
            $table->text('description')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->json('specifications')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'category']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->date('expected_date')->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('equipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_request_id')->nullable()->constrained('equipment_requests')->nullOnDelete();
            $table->string('serial_number', 120)->nullable();
            $table->string('status', 20)->default('assigned');
            $table->date('assigned_date')->nullable();
            $table->text('issue_note')->nullable();
            $table->timestamp('issue_reported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_assignments');
        Schema::dropIfExists('equipment_requests');
        Schema::dropIfExists('equipments');
    }
};
