<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('employee_name');
            $table->string('designation')->nullable();
            $table->string('department_name')->nullable();
            $table->string('location')->nullable();
            $table->date('joining_date')->nullable();
            $table->decimal('payable_days', 5, 1)->default(0);
            $table->decimal('lop_days', 5, 1)->default(0);
            $table->json('earnings');
            $table->json('deductions');
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->decimal('expense_reimbursements', 12, 2)->default(0);
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('uan')->nullable();
            $table->string('pf_number')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
