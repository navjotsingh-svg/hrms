<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payment_methods', function (Blueprint $table) {
            $table->string('bank_branch')->nullable()->after('bank_name');
            $table->string('bank_address')->nullable()->after('bank_branch');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payment_methods', function (Blueprint $table) {
            $table->dropColumn(['bank_branch', 'bank_address']);
        });
    }
};
