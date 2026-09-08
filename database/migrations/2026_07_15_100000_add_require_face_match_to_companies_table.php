<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || Schema::hasColumn('companies', 'attendance_require_face_match')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $column = $table->boolean('attendance_require_face_match')->nullable();

            if (Schema::hasColumn('companies', 'attendance_face_match_threshold')) {
                $column->after('attendance_face_match_threshold');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'attendance_require_face_match')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('attendance_require_face_match');
        });
    }
};
