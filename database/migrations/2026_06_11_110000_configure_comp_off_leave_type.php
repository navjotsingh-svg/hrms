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
=======
        LeaveType::query()
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
            ->whereIn('code', ['COMP', 'CO'])
            ->update([
                'name' => 'Comp Off',
                'annual_quota' => 0,
<<<<<<< HEAD
                'updated_at' => now(),
=======
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
            ]);
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
