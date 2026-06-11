<?php

use App\Models\LeaveType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        LeaveType::query()
            ->whereIn('code', ['COMP', 'CO'])
            ->update([
                'name' => 'Comp Off',
                'annual_quota' => 0,
            ]);
    }

    public function down(): void
    {
        // Data migration — no rollback.
    }
};
