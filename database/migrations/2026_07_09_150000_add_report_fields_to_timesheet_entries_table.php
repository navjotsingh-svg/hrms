<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->text('done_today')->nullable()->after('notes');
            $table->text('blockers')->nullable()->after('done_today');
            $table->text('plan_tomorrow')->nullable()->after('blockers');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropColumn(['done_today', 'blockers', 'plan_tomorrow']);
        });
    }
};
