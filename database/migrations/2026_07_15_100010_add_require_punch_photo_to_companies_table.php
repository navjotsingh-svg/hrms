<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || Schema::hasColumn('companies', 'attendance_require_punch_photo')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $column = $table->boolean('attendance_require_punch_photo')->nullable();

            if (Schema::hasColumn('companies', 'attendance_require_face_match')) {
                $column->after('attendance_require_face_match');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'attendance_require_punch_photo')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('attendance_require_punch_photo');
        });
    }
};
