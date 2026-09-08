<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('companies', 'attendance_face_match_threshold')) {
            DB::table('companies')->update([
                'attendance_face_match_threshold' => 90,
            ]);
        }

        if (Schema::hasColumn('employees', 'profile_face_descriptor')) {
            DB::table('employees')
                ->whereNotNull('profile_face_descriptor')
                ->update(['profile_face_descriptor' => null]);
        }
    }

    public function down(): void
    {
        // Previous embedding model descriptors cannot be restored.
    }
};
