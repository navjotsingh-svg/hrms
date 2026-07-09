<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_on_one_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->text('agenda')->nullable();
            $table->text('meeting_notes')->nullable();
            $table->json('action_items')->nullable();
            $table->string('google_meet_link')->nullable();
            $table->string('google_calendar_link')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'scheduled_at']);
            $table->index(['company_id', 'employee_id', 'status']);
            $table->index(['company_id', 'organizer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_on_one_meetings');
    }
};
