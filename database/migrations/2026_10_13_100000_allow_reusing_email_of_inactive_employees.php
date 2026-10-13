<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'email']);
            $table->string('active_email_key', 320)->nullable()->after('email');
        });

        DB::table('employees')
            ->where('status', 'active')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $employee) {
                DB::table('employees')->where('id', $employee->id)->update([
                    'active_email_key' => $employee->company_id.'#'.strtolower((string) $employee->email),
                ]);
            });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('active_email_key');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['active_email_key']);
            $table->dropColumn('active_email_key');
            $table->unique(['company_id', 'email']);
        });
    }
};
