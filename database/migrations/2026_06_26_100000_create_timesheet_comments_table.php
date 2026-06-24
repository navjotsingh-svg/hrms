<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('timesheet_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_comments');
    }
};
