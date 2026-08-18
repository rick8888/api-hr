<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('department', 120)->nullable();
            $table->string('location', 180)->nullable();
            $table->string('type', 40)->default('full_time');
            $table->string('status', 20)->default('open');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'department']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_opening_id')->nullable()->constrained('job_openings')->nullOnDelete();
            $table->string('name', 180);
            $table->string('email', 180);
            $table->string('phone', 60)->nullable();
            $table->string('position', 180)->nullable();
            $table->string('experience', 120)->nullable();
            $table->string('source', 80)->nullable();
            $table->string('availability', 120)->nullable();
            $table->text('summary')->nullable();
            $table->string('stage', 40)->default('applied');
            $table->string('last_action', 180)->nullable();
            $table->date('applied_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'stage']);
            $table->index(['company_id', 'job_opening_id']);
            $table->index(['company_id', 'applied_date']);
            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
    }
};
