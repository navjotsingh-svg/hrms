<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('annual_ctc', 12, 2);
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('hra', 12, 2)->default(0);
            $table->decimal('special_allowance', 12, 2)->default(0);
            $table->decimal('conveyance_allowance', 12, 2)->default(0);
            $table->decimal('medical_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->boolean('pf_applicable')->default(true);
            $table->boolean('esi_applicable')->default(false);
            $table->boolean('professional_tax_applicable')->default(true);
            $table->enum('payment_mode', ['bank_transfer', 'cash', 'cheque'])->default('bank_transfer');
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->date('salary_effective_from');
            $table->timestamps();

            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
