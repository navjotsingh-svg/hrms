<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->decimal('hra_percent', 5, 2)->default(40)->after('basic_salary');
            $table->decimal('special_allowance_percent', 5, 2)->default(0)->after('hra_percent');
        });

        DB::table('employee_salaries')->get()->each(function ($salary) {
            $basic = (float) $salary->basic_salary;

            if ($basic <= 0) {
                return;
            }

            DB::table('employee_salaries')->where('id', $salary->id)->update([
                'hra_percent' => round(((float) $salary->hra / $basic) * 100, 2),
                'special_allowance_percent' => round(((float) $salary->special_allowance / $basic) * 100, 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropColumn(['hra_percent', 'special_allowance_percent']);
        });
    }
};
