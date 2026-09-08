<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_review_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft');
            $table->boolean('reviews_open')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('performance_review_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_review_cycles')->cascadeOnDelete();
            $table->string('question');
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('performance_review_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_review_cycles')->cascadeOnDelete();
            $table->foreignId('reviewee_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('relationship', 20)->default('manager');
            $table->timestamps();

            $table->unique(['cycle_id', 'reviewee_employee_id', 'reviewer_employee_id', 'relationship'], 'review_pair_unique');
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_review_cycles')->cascadeOnDelete();
            $table->foreignId('pair_id')->constrained('performance_review_pairs')->cascadeOnDelete();
            $table->foreignId('reviewee_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('not_started');
            $table->decimal('overall_rating', 5, 2)->nullable();
            $table->text('summary_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['cycle_id', 'status']);
        });

        Schema::create('performance_review_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('performance_review_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'question_id']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('visibility', 20)->default('team');
            $table->decimal('progress', 5, 2)->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'status']);
        });

        Schema::create('goal_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('target_value', 12, 2)->default(100);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->string('unit', 50)->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('status', 20)->default('not_started');
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pip_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('title');
            $table->text('reason')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->text('outcome_notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('pip_key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pip_plan_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pip_key_results');
        Schema::dropIfExists('pip_plans');
        Schema::dropIfExists('goal_key_results');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('performance_review_answers');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_review_pairs');
        Schema::dropIfExists('performance_review_questions');
        Schema::dropIfExists('performance_review_cycles');
    }
};
