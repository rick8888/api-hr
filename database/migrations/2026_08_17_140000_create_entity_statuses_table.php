<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 80);
            $table->string('code', 80);
            $table->string('name', 120);
            $table->string('name_en', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('tone', 20)->default('neutral');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity_type', 'code']);
            $table->index(['entity_type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_statuses');
    }
};
