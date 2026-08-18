<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->date('date');
            $table->string('type', 40);
            $table->string('category', 40)->nullable();
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#22c55e');
            $table->string('applicable_to', 40)->default('all');
            $table->boolean('recurring')->default(false);
            $table->boolean('optional_holiday')->default(false);
            $table->boolean('restricted_holiday')->default(false);
            $table->boolean('half_day')->default(false);
            $table->boolean('visible_to_employees')->default(true);
            $table->boolean('send_notification')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
