<?php

use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LeaveType::query()
            ->where('code', 'CL')
            ->whereNull('max_days_per_request')
            ->update(['max_days_per_request' => 2]);
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
