<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_request_items', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('asset_type_id');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->text('review_notes')->nullable()->after('reviewed_at');

            $table->index(['asset_request_id', 'status']);
        });

        DB::table('asset_request_items')
            ->join('asset_requests', 'asset_requests.id', '=', 'asset_request_items.asset_request_id')
            ->select([
                'asset_request_items.id',
                'asset_requests.status as request_status',
                'asset_requests.reviewed_by_user_id',
                'asset_requests.reviewed_at',
                'asset_requests.review_notes',
            ])
            ->orderBy('asset_request_items.id')
            ->get()
            ->each(function ($row) {
                $itemStatus = match ($row->request_status) {
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    default => 'pending',
                };

                DB::table('asset_request_items')
                    ->where('id', $row->id)
                    ->update([
                        'status' => $itemStatus,
                        'reviewed_by_user_id' => $itemStatus === 'pending' ? null : $row->reviewed_by_user_id,
                        'reviewed_at' => $itemStatus === 'pending' ? null : $row->reviewed_at,
                        'review_notes' => $itemStatus === 'pending' ? null : $row->review_notes,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('asset_request_items', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropIndex(['asset_request_id', 'status']);
            $table->dropColumn(['status', 'reviewed_by_user_id', 'reviewed_at', 'review_notes']);
        });
    }
};
