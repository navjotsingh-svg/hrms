<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'project_id', 'work_date']);
            $table->index(['company_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_entries');
    }
};
