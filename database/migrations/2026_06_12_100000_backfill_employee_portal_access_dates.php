<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('
                UPDATE employees
                SET portal_access_date = date(created_at)
                WHERE user_id IS NOT NULL
                  AND portal_access_date IS NULL
            ');

            return;
        }

        DB::statement('
            UPDATE employees AS e
            INNER JOIN users AS u ON e.user_id = u.id
            SET e.portal_access_date = DATE(u.created_at)
            WHERE e.portal_access_date IS NULL
        ');

        DB::table('employees')
            ->whereNotNull('user_id')
            ->whereNull('portal_access_date')
            ->update([
                'portal_access_date' => DB::raw('DATE(created_at)'),
            ]);
    }

    public function down(): void
    {
        // Non-destructive data backfill.
    }
};
