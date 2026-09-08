<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_requisitions') || Schema::hasColumn('job_requisitions', 'approval_notes')) {
            return;
        }

        Schema::table('job_requisitions', function (Blueprint $table) {
            $table->text('approval_notes')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('job_requisitions') || ! Schema::hasColumn('job_requisitions', 'approval_notes')) {
            return;
        }

        Schema::table('job_requisitions', function (Blueprint $table) {
            $table->dropColumn('approval_notes');
        });
    }
};
