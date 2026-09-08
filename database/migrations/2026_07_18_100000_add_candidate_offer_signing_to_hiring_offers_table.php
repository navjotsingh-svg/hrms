<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hiring_offers', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->unique()->after('responded_at');
            $table->timestamp('token_expires_at')->nullable()->after('access_token');
            $table->string('pdf_path')->nullable()->after('token_expires_at');
            $table->string('signed_pdf_path')->nullable()->after('pdf_path');
            $table->string('signature_name')->nullable()->after('signed_pdf_path');
            $table->string('signature_image_path')->nullable()->after('signature_name');
            $table->timestamp('signed_at')->nullable()->after('signature_image_path');
            $table->string('signature_ip', 45)->nullable()->after('signed_at');
            $table->json('signature_meta')->nullable()->after('signature_ip');
            $table->text('decline_reason')->nullable()->after('signature_meta');
            $table->timestamp('otp_verified_at')->nullable()->after('decline_reason');
        });

        Schema::create('hiring_offer_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_offer_id')->constrained('hiring_offers')->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['hiring_offer_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_offer_verifications');

        Schema::table('hiring_offers', function (Blueprint $table) {
            $table->dropColumn([
                'access_token',
                'token_expires_at',
                'pdf_path',
                'signed_pdf_path',
                'signature_name',
                'signature_image_path',
                'signed_at',
                'signature_ip',
                'signature_meta',
                'decline_reason',
                'otp_verified_at',
            ]);
        });
    }
};
