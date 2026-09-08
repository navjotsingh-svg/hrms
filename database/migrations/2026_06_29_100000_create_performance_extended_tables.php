<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('question');
            $table->string('question_type', 20)->default('rating');
            $table->decimal('default_weight', 8, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('performance_feedback_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('performance_feedback_form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_form_id')->constrained('performance_feedback_forms')->cascadeOnDelete();
            $table->foreignId('question_bank_id')->nullable()->constrained('performance_question_bank')->nullOnDelete();
            $table->string('question');
            $table->string('question_type', 20)->default('rating');
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('performance_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('target_value', 12, 2)->default(100);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->string('unit', 50)->nullable();
            $table->string('frequency', 20)->default('quarterly');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_kpis');
        Schema::dropIfExists('performance_feedback_form_questions');
        Schema::dropIfExists('performance_feedback_forms');
        Schema::dropIfExists('performance_question_bank');
    }
};
