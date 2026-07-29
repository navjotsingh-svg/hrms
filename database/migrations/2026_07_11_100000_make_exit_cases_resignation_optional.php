<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exit_cases', function (Blueprint $table) {
            $table->dropForeign(['resignation_request_id']);
            $table->dropUnique(['resignation_request_id']);
        });

        Schema::table('exit_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('resignation_request_id')->nullable()->change();
            $table->string('exit_type', 30)->default('resignation')->after('employee_id');
            $table->foreign('resignation_request_id')->references('id')->on('resignation_requests')->nullOnDelete();
            $table->unique('resignation_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('exit_cases', function (Blueprint $table) {
            $table->dropForeign(['resignation_request_id']);
            $table->dropUnique(['resignation_request_id']);
            $table->dropColumn('exit_type');
        });

        Schema::table('exit_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('resignation_request_id')->nullable(false)->change();
            $table->foreign('resignation_request_id')->references('id')->on('resignation_requests')->cascadeOnDelete();
            $table->unique('resignation_request_id');
        });
    }
};
