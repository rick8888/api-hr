<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();

            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();

            // monthly | quarterly | annual
            $table->string('frequency')->default('monthly');
            $table->unsignedTinyInteger('deadline_day_of_month')->default(15);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_obligations');
    }
};
