<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('income_tax_applicable')->default(false)->after('professional_tax_applicable');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('tax_regime', 10)->default('new')->after('pan_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tax_regime');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('income_tax_applicable');
        });
    }
};
