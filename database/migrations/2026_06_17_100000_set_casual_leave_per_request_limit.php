<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_types')
            ->where('code', 'CL')
            ->whereNull('max_days_per_request')
            ->update([
                'max_days_per_request' => 2,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
