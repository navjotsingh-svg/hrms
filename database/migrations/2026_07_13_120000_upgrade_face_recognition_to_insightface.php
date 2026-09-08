<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->update([
            'attendance_face_match_threshold' => 90,
        ]);

        DB::table('employees')
            ->whereNotNull('profile_face_descriptor')
            ->update(['profile_face_descriptor' => null]);
    }

    public function down(): void
    {
        // Previous embedding model descriptors cannot be restored.
    }
};
