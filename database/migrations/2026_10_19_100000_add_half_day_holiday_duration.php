<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('holidays') || ! Schema::hasColumn('holidays', 'duration')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE holidays MODIFY duration ENUM('single', 'range', 'half') NOT NULL DEFAULT 'single'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('holidays') || ! Schema::hasColumn('holidays', 'duration')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('holidays')->where('duration', 'half')->update(['duration' => 'single']);
        DB::statement("ALTER TABLE holidays MODIFY duration ENUM('single', 'range') NOT NULL DEFAULT 'single'");
    }
};
