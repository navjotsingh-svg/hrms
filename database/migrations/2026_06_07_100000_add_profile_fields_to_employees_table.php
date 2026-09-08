<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('pan_number', 10)->nullable()->after('postal_code');
            $table->string('aadhaar_number', 12)->nullable()->after('pan_number');
            $table->string('uan', 12)->nullable()->after('aadhaar_number');
            $table->string('pf_number', 30)->nullable()->after('uan');
            $table->string('esi_number', 30)->nullable()->after('pf_number');
            $table->string('emergency_contact_name')->nullable()->after('esi_number');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_relation', 50)->nullable()->after('emergency_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'pan_number',
                'aadhaar_number',
                'uan',
                'pf_number',
                'esi_number',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relation',
            ]);
        });
    }
};
