<?php

use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('allows_attendance_punch')
                ->default(false)
                ->after('is_paid');
        });

        LeaveType::withTrashed()
            ->where(function ($query) {
                $query->whereIn('code', ['WFH', 'WFHOME'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%work from home%']);
            })
            ->update(['allows_attendance_punch' => true]);
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('allows_attendance_punch');
        });
    }
};
