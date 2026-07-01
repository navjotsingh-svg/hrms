<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['asset_request_id', 'asset_type_id']);
            $table->index(['asset_type_id']);
        });

        if (Schema::hasColumn('asset_requests', 'asset_type_id')) {
            DB::table('asset_requests')
                ->whereNotNull('asset_type_id')
                ->orderBy('id')
                ->get()
                ->each(function ($row) {
                    DB::table('asset_request_items')->insert([
                        'asset_request_id' => $row->id,
                        'asset_type_id' => $row->asset_type_id,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                });

            Schema::table('asset_requests', function (Blueprint $table) {
                $table->dropForeign(['asset_type_id']);
                $table->dropColumn('asset_type_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('asset_requests', 'asset_type_id')) {
            Schema::table('asset_requests', function (Blueprint $table) {
                $table->foreignId('asset_type_id')->nullable()->after('employee_id')->constrained()->cascadeOnDelete();
            });

            DB::table('asset_request_items')
                ->orderBy('id')
                ->get()
                ->groupBy('asset_request_id')
                ->each(function ($items, $requestId) {
                    $first = $items->first();

                    if ($first) {
                        DB::table('asset_requests')
                            ->where('id', $requestId)
                            ->update(['asset_type_id' => $first->asset_type_id]);
                    }
                });
        }

        Schema::dropIfExists('asset_request_items');
    }
};
