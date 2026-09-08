<?php

use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_leave_types')) {
            Schema::create('employee_leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'leave_type_id']);
            });
        }

        $now = now();

        Employee::query()
            ->select(['id', 'company_id'])
            ->orderBy('id')
            ->chunkById(100, function ($employees) use ($now) {
                foreach ($employees as $employee) {
                    $leaveTypeIds = LeaveType::query()
                        ->where('company_id', $employee->company_id)
                        ->where('status', 'active')
                        ->whereNull('deleted_at')
                        ->pluck('id');

                    foreach ($leaveTypeIds as $leaveTypeId) {
                        DB::table('employee_leave_types')->insertOrIgnore([
                            'company_id' => $employee->company_id,
                            'employee_id' => $employee->id,
                            'leave_type_id' => $leaveTypeId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_types');
    }
};
