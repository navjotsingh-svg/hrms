<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('role_slug', 50)->nullable();
            $table->string('module', 50);
            $table->string('action', 100);
            $table->string('status', 20)->default('success');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('request_type', 80)->nullable();
            $table->text('message')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('action_note')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['company_id', 'logged_at']);
            $table->index(['employee_id', 'logged_at']);
            $table->index(['module', 'action']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['request_type', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
