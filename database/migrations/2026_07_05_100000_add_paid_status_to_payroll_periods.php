<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payroll_periods MODIFY status ENUM('processed', 'paid') NOT NULL DEFAULT 'processed'");

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignId('paid_by_user_id')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_user_id');
            $table->dropColumn('paid_at');
        });

        DB::table('payroll_periods')->where('status', 'paid')->update(['status' => 'processed']);

        DB::statement("ALTER TABLE payroll_periods MODIFY status ENUM('processed') NOT NULL DEFAULT 'processed'");
    }
};
