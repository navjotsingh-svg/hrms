<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('attendance_allowed_ips')->nullable()->after('attendance_portal_start_date');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->json('profile_face_descriptor')->nullable()->after('profile_photo_path');
        });

        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('selfie_path');
            $table->decimal('face_match_score', 5, 2)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'face_match_score']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('profile_face_descriptor');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('attendance_allowed_ips');
        });
    }
};
