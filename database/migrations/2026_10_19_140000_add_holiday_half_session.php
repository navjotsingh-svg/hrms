<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('holidays') || Schema::hasColumn('holidays', 'half_session')) {
            return;
        }

        Schema::table('holidays', function (Blueprint $table) {
            $table->string('half_session', 20)->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('holidays') || ! Schema::hasColumn('holidays', 'half_session')) {
            return;
        }

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn('half_session');
        });
    }
};
