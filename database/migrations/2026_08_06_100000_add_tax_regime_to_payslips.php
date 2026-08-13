<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->boolean('income_tax_applicable')->default(false)->after('pf_number');
            $table->string('tax_regime', 10)->nullable()->after('income_tax_applicable');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['income_tax_applicable', 'tax_regime']);
        });
    }
};
