<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'department_id']);
        });

        if (Schema::hasColumn('employees', 'department_id')) {
            $rows = DB::table('employees')
                ->whereNotNull('department_id')
                ->get(['id', 'department_id']);

            foreach ($rows as $row) {
                DB::table('department_employee')->insertOrIgnore([
                    'employee_id' => $row->id,
                    'department_id' => $row->department_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_employee');
    }
};
