<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 80);
            $table->string('family', 40);
            $table->string('channel', 20)->default('pdf');
            $table->string('locale', 5)->default('fr');
            $table->string('subject', 255)->nullable();
            $table->longText('body_html');
            $table->string('document_category_code', 80)->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code', 'locale'], 'document_templates_company_code_locale_unique');
            $table->index(['family', 'channel', 'status']);
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_code', 80);
            $table->unsignedSmallInteger('template_version')->default(1);
            $table->string('name', 200);
            $table->string('file_path')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->foreignId('employee_document_id')->nullable()->constrained('employee_documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_templates');
    }
};
