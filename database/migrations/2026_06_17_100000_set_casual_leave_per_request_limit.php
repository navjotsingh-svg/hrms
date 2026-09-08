<?php

<<<<<<< HEAD
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
=======
use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9

return new class extends Migration
{
    public function up(): void
    {
<<<<<<< HEAD
        DB::table('leave_types')
            ->where('code', 'CL')
            ->whereNull('max_days_per_request')
            ->update([
                'max_days_per_request' => 2,
                'updated_at' => now(),
            ]);
=======
        LeaveType::query()
            ->where('code', 'CL')
            ->whereNull('max_days_per_request')
            ->update(['max_days_per_request' => 2]);
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
