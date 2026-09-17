<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_policy_consents')) {
            return;
        }

        Schema::create('company_policy_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('company_policy_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('policy_version')->default(1);
            $table->string('consent_email');
            $table->string('signature_name');
            $table->string('signature_image_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_ip', 45)->nullable();
            $table->json('signature_meta')->nullable();
            $table->timestamps();

            $table->unique(['company_policy_id', 'employee_id'], 'company_policy_consents_policy_employee_unique');
            $table->index(['company_id', 'employee_id']);
            $table->index('company_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_policy_consents');
    }
};
