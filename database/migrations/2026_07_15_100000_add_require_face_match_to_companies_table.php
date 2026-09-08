<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
<<<<<<< HEAD
        if (! Schema::hasTable('companies') || Schema::hasColumn('companies', 'attendance_require_face_match')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $column = $table->boolean('attendance_require_face_match')->nullable();

            if (Schema::hasColumn('companies', 'attendance_face_match_threshold')) {
                $column->after('attendance_face_match_threshold');
            }
=======
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('attendance_require_face_match')
                ->nullable()
                ->after('attendance_face_match_threshold');
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
        });
    }

    public function down(): void
    {
<<<<<<< HEAD
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'attendance_require_face_match')) {
            return;
        }

=======
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('attendance_require_face_match');
        });
    }
};
