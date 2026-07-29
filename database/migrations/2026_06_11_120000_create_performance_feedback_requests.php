<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_feedback_requests')) {
            Schema::create('performance_feedback_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('feedback_form_id')->constrained('performance_feedback_forms')->cascadeOnDelete();
                $table->foreignId('subject_employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('reviewer_employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('pending');
                $table->text('context_notes')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('overall_rating', 4, 2)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['reviewer_employee_id', 'status']);
                $table->index(['subject_employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('performance_feedback_request_answers')) {
            Schema::create('performance_feedback_request_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_id')->constrained('performance_feedback_requests')->cascadeOnDelete();
                $table->foreignId('form_question_id')->constrained('performance_feedback_form_questions')->cascadeOnDelete();
                $table->unsignedTinyInteger('rating')->nullable();
                $table->text('response_text')->nullable();
                $table->timestamps();

                $table->unique(['request_id', 'form_question_id'], 'pf_req_answers_req_q_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_feedback_request_answers');
        Schema::dropIfExists('performance_feedback_requests');
    }
};
