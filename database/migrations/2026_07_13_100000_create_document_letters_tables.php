<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_letter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40)->default('other');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->longText('body_html');
            $table->boolean('requires_signature')->default(true);
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('document_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('document_letter_templates')->nullOnDelete();
            $table->string('document_number', 40);
            $table->string('title');
            $table->string('category', 40)->default('other');
            $table->longText('rendered_html');
            $table->string('status', 30)->default('draft');
            $table->boolean('requires_signature')->default(true);
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_ip', 45)->nullable();
            $table->json('signature_meta')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_letters');
        Schema::dropIfExists('document_letter_templates');
    }
};
