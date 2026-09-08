<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('basic_salary_percent', 5, 2)->default(50)->after('professional_tax_applicable');
            $table->decimal('hra_percent', 5, 2)->default(40)->after('basic_salary_percent');
            $table->decimal('special_allowance_percent', 5, 2)->default(0)->after('hra_percent');
            $table->decimal('conveyance_allowance', 12, 2)->default(0)->after('special_allowance_percent');
            $table->decimal('medical_allowance', 12, 2)->default(0)->after('conveyance_allowance');
            $table->decimal('other_allowance', 12, 2)->default(0)->after('medical_allowance');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'basic_salary_percent',
                'hra_percent',
                'special_allowance_percent',
                'conveyance_allowance',
                'medical_allowance',
                'other_allowance',
            ]);
        });
    }
};
