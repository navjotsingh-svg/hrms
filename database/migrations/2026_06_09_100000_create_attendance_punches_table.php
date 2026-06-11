<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('punch_type', ['in', 'out']);
            $table->dateTime('punched_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('selfie_path');
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);
            $table->index(['company_id', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
