<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('pf_applicable')->default(true)->after('attendance_portal_start_date');
            $table->boolean('esi_applicable')->default(false)->after('pf_applicable');
            $table->boolean('professional_tax_applicable')->default(true)->after('esi_applicable');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'pf_applicable',
                'esi_applicable',
                'professional_tax_applicable',
            ]);
        });
    }
};
