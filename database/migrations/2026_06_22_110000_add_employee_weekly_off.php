<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'weekly_off_mode')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->enum('weekly_off_mode', ['company_default', 'custom'])
                    ->default('company_default')
                    ->after('shift_id');
            });
        }

        if (! Schema::hasTable('employee_weekly_off_days')) {
            Schema::create('employee_weekly_off_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('weekday');
                $table->timestamps();

                $table->unique(['employee_id', 'weekday']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_weekly_off_days')) {
            Schema::dropIfExists('employee_weekly_off_days');
        }

        if (Schema::hasColumn('employees', 'weekly_off_mode')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('weekly_off_mode');
            });
        }
    }
};
