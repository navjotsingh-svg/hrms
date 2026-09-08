<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_comments', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('work_date')
                ->constrained()
                ->cascadeOnDelete();

            $table->index(['employee_id', 'work_date', 'project_id'], 'timesheet_comments_employee_date_project_idx');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_comments', function (Blueprint $table) {
            $table->dropIndex('timesheet_comments_employee_date_project_idx');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
