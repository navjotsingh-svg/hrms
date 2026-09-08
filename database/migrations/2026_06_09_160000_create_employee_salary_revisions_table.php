<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('annual_ctc', 12, 2);
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('hra_percent', 5, 2)->default(0);
            $table->decimal('special_allowance_percent', 5, 2)->default(0);
            $table->decimal('hra', 12, 2)->default(0);
            $table->decimal('special_allowance', 12, 2)->default(0);
            $table->decimal('conveyance_allowance', 12, 2)->default(0);
            $table->decimal('medical_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->boolean('pf_applicable')->default(true);
            $table->boolean('esi_applicable')->default(false);
            $table->boolean('professional_tax_applicable')->default(true);
            $table->date('salary_effective_from')->nullable();
            $table->text('revision_notes')->nullable();
            $table->timestamp('revised_at');
            $table->timestamps();

            $table->index(['employee_id', 'revised_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_revisions');
    }
};
