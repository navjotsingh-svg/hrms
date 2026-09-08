<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_regularization_requests', function (Blueprint $table) {
            $table->foreignId('supersedes_request_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('attendance_regularization_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_regularization_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_request_id');
        });
    }
};
