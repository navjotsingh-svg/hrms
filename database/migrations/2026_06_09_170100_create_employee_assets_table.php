<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_assigned')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'asset_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assets');
    }
};
