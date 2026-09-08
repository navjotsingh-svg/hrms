<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_types')
            ->whereIn('code', ['COMP', 'CO'])
            ->update([
                'name' => 'Comp Off',
                'annual_quota' => 0,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
