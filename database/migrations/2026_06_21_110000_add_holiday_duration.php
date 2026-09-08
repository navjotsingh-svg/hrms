<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('holidays', 'duration')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->enum('duration', ['single', 'range'])->default('single')->after('frequency');
            });
        }

        DB::table('holidays')->update([
            'duration' => DB::raw("CASE WHEN DATE(`from_date`) = DATE(`to_date`) THEN 'single' ELSE 'range' END"),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('holidays', 'duration')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropColumn('duration');
            });
        }
    }
};
