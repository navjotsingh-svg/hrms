<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('company_policies', 'body_html')) {
                $table->longText('body_html')->nullable()->after('description');
            }

            if (! Schema::hasColumn('company_policies', 'requires_consent')) {
                $table->boolean('requires_consent')->default(true)->after('status');
            }
        });

        // Soften file columns so page-based policies do not need an uploaded file.
        try {
            DB::statement('ALTER TABLE company_policies MODIFY original_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE company_policies MODIFY file_path VARCHAR(255) NULL');
        } catch (\Throwable) {
            // Ignore if the engine already allows nulls.
        }
    }

    public function down(): void
    {
        Schema::table('company_policies', function (Blueprint $table) {
            if (Schema::hasColumn('company_policies', 'body_html')) {
                $table->dropColumn('body_html');
            }

            if (Schema::hasColumn('company_policies', 'requires_consent')) {
                $table->dropColumn('requires_consent');
            }
        });
    }
};
