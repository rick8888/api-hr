<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_services', function (Blueprint $table) {
            $table->string('code', 60)->primary();
            $table->string('label');
            $table->string('category', 40)->default('core');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_employees')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('included_services')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscription_services');
    }
};
