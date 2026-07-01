<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resignation_requests', function (Blueprint $table) {
            $table->dropForeign(['applied_by_user_id']);
        });

        DB::statement('ALTER TABLE resignation_requests MODIFY applied_by_user_id BIGINT UNSIGNED NULL');

        Schema::table('resignation_requests', function (Blueprint $table) {
            $table->foreign('applied_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resignation_requests', function (Blueprint $table) {
            $table->dropForeign(['applied_by_user_id']);
        });

        DB::statement('ALTER TABLE resignation_requests MODIFY applied_by_user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('resignation_requests', function (Blueprint $table) {
            $table->foreign('applied_by_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
