<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('holidays', 'frequency')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->enum('frequency', ['fixed', 'variable'])->default('fixed')->after('name');
            });
        }

        if (! Schema::hasColumn('holidays', 'from_date')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->date('from_date')->nullable()->after('date');
                $table->date('to_date')->nullable()->after('from_date');
            });
        }

        DB::table('holidays')
            ->whereNull('from_date')
            ->update([
                'from_date' => DB::raw('`date`'),
                'to_date' => DB::raw('`date`'),
                'frequency' => DB::raw("COALESCE(`frequency`, 'fixed')"),
            ]);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE holidays MODIFY COLUMN type ENUM('public', 'company', 'optional', 'other') NOT NULL DEFAULT 'company'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE holidays MODIFY COLUMN type ENUM('public', 'company', 'optional') NOT NULL DEFAULT 'company'");
        }

        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'frequency')) {
                $table->dropColumn('frequency');
            }

            if (Schema::hasColumn('holidays', 'from_date')) {
                $table->dropColumn(['from_date', 'to_date']);
            }
        });
    }
};
