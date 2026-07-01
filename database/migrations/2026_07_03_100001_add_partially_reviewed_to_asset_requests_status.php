<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE asset_requests MODIFY status ENUM('pending', 'approved', 'rejected', 'cancelled', 'partially_reviewed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('asset_requests')
            ->where('status', 'partially_reviewed')
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE asset_requests MODIFY status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
    }
};
