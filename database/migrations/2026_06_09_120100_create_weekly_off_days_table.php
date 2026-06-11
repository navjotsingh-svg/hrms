<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_off_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->timestamps();

            $table->unique(['company_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_off_days');
    }
};
