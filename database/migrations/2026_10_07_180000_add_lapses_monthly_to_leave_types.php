<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('lapses_monthly')->default(false)->after('max_days_per_month');
        });

        DB::table('leave_types')
            ->where('code', 'CL')
            ->update([
                'annual_quota' => 12,
                'max_days_per_request' => 1,
                'max_days_per_month' => 1,
                'lapses_monthly' => true,
                'is_paid' => true,
                'updated_at' => now(),
            ]);

        DB::table('leave_types')
            ->where('code', 'SL')
            ->update([
                'annual_quota' => 6,
                'max_days_per_month' => null,
                'lapses_monthly' => false,
                'is_paid' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('lapses_monthly');
        });
    }
};
