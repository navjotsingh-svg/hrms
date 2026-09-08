<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->string('source', 20)->default('live')->after('selfie_path');
            $table->foreignId('regularization_request_id')
                ->nullable()
                ->after('source')
                ->constrained('attendance_regularization_requests')
                ->nullOnDelete();
            $table->string('selfie_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_punches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regularization_request_id');
            $table->dropColumn('source');
            $table->string('selfie_path')->nullable(false)->change();
        });
    }
};
