<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('probation_applicable')->default(true)->after('status');
            $table->unsignedTinyInteger('probation_period_months')->nullable()->after('probation_applicable');
            $table->date('probation_end_date')->nullable()->after('probation_period_months');
            $table->enum('probation_status', ['on_probation', 'confirmed', 'extended', 'not_applicable'])
                ->default('on_probation')
                ->after('probation_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'probation_applicable',
                'probation_period_months',
                'probation_end_date',
                'probation_status',
            ]);
        });
    }
};
