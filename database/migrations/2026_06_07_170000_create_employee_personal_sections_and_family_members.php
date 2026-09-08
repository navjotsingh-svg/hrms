<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_personal_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('section_type', ['family', 'address', 'emergency_contact']);
            $table->json('payload');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'section_type']);
        });

        Schema::create('employee_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relation', 50);
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('temp_address_line_1')->nullable()->after('postal_code');
            $table->string('temp_address_line_2')->nullable()->after('temp_address_line_1');
            $table->string('temp_city', 100)->nullable()->after('temp_address_line_2');
            $table->string('temp_state', 100)->nullable()->after('temp_city');
            $table->string('temp_country', 100)->nullable()->after('temp_state');
            $table->string('temp_postal_code', 20)->nullable()->after('temp_country');
            $table->foreignId('emergency_contact_family_member_id')
                ->nullable()
                ->after('emergency_contact_relation')
                ->constrained('employee_family_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('emergency_contact_family_member_id');
            $table->dropColumn([
                'temp_address_line_1',
                'temp_address_line_2',
                'temp_city',
                'temp_state',
                'temp_country',
                'temp_postal_code',
            ]);
        });

        Schema::dropIfExists('employee_family_members');
        Schema::dropIfExists('employee_personal_sections');
    }
};
