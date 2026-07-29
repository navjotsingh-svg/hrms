<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goal_key_results', function (Blueprint $table) {
            $table->foreignId('performance_kpi_id')
                ->nullable()
                ->after('goal_id')
                ->constrained('performance_kpis')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goal_key_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('performance_kpi_id');
        });
    }
};
