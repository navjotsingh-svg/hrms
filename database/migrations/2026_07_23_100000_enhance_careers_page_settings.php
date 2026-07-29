<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('careers_page_settings', function (Blueprint $table) {
            $table->string('hero_cta_text')->nullable()->after('hero_subtitle');
            $table->string('hero_cta_url')->nullable()->after('hero_cta_text');
            $table->string('theme_primary', 20)->default('#0f172a')->after('logo_path');
            $table->string('theme_accent', 20)->default('#2563eb')->after('theme_primary');
            $table->json('sections')->nullable()->after('theme_accent');
        });
    }

    public function down(): void
    {
        Schema::table('careers_page_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_cta_text',
                'hero_cta_url',
                'theme_primary',
                'theme_accent',
                'sections',
            ]);
        });
    }
};
