<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('type', 40);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('interest_rate', 6, 2)->default(0);
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('emi', 14, 2)->default(0);
            $table->decimal('outstanding', 14, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->date('repayment_start_date')->nullable();
            $table->json('documents')->nullable();
            $table->string('manager_name', 120)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
