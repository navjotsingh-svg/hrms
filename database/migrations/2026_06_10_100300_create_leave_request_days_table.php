<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('session', ['full_day', 'first_half', 'second_half'])->default('full_day');
            $table->decimal('day_value', 3, 1)->default(1);
            $table->timestamps();

            $table->unique(['leave_request_id', 'date', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_days');
    }
};
