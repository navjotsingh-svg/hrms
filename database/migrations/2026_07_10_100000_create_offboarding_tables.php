<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resignation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('proposed_last_working_date');
            $table->date('approved_last_working_date')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('exit_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resignation_request_id')->constrained()->cascadeOnDelete();
            $table->date('last_working_date');
            $table->enum('stage', ['clearance', 'asset_return', 'survey', 'fnf', 'completed'])->default('clearance');
            $table->enum('status', ['in_progress', 'completed', 'cancelled'])->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('resignation_request_id');
            $table->index(['company_id', 'status']);
        });

        Schema::create('exit_clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_case_id')->constrained()->cascadeOnDelete();
            $table->string('department_key', 50);
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['pending', 'cleared', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['exit_case_id', 'department_key']);
        });

        Schema::create('exit_asset_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnDelete();
            $table->string('asset_name');
            $table->enum('status', ['pending', 'returned', 'waived'])->default('pending');
            $table->text('condition_notes')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->unique(['exit_case_id', 'asset_type_id']);
        });

        Schema::create('exit_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->json('responses');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique('exit_case_id');
        });

        Schema::create('full_and_final_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exit_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('leave_encashment', 12, 2)->default(0);
            $table->decimal('pending_dues', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_payable', 12, 2)->default(0);
            $table->text('settlement_notes')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique('exit_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('full_and_final_settlements');
        Schema::dropIfExists('exit_survey_responses');
        Schema::dropIfExists('exit_asset_return_items');
        Schema::dropIfExists('exit_clearance_items');
        Schema::dropIfExists('exit_cases');
        Schema::dropIfExists('resignation_requests');
    }
};
