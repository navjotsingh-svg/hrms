<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->string('industry')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('employee_strength', 50)->nullable();
            $table->string('registration_number')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_phone', 20)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('India');
            $table->string('postal_code', 10)->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
