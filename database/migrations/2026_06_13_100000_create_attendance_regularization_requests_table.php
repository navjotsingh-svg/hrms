<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_regularization_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->dateTime('requested_punch_in')->nullable();
            $table->dateTime('requested_punch_out')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('applied_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'att_reg_company_status_idx');
            $table->index(['employee_id', 'attendance_date'], 'att_reg_employee_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularization_requests');
    }
};
