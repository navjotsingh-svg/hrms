<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_regularization_requests', function (Blueprint $table) {
            $table->dateTime('original_punch_in')->nullable()->after('requested_punch_out');
            $table->dateTime('original_punch_out')->nullable()->after('original_punch_in');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_regularization_requests', function (Blueprint $table) {
            $table->dropColumn(['original_punch_in', 'original_punch_out']);
        });
    }
};
