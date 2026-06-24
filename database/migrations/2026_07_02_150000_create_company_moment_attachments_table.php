<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_moment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_moment_id')->constrained('company_moments')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 120);
            $table->unsignedInteger('file_size')->default(0);
            $table->timestamps();

            $table->index('company_moment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_moment_attachments');
    }
};
