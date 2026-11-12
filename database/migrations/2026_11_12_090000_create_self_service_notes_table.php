<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_service_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->json('tags')->nullable();
            $table->json('checklist')->nullable();
            $table->boolean('pinned')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_service_notes');
    }
};
