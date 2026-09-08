<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_calibration_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('performance_review_cycles')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('performance_calibration_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('performance_calibration_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->decimal('original_rating', 5, 2)->nullable();
            $table->decimal('calibrated_rating', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['session_id', 'employee_id']);
        });

        Schema::create('promotion_nominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('current_designation')->nullable();
            $table->string('proposed_designation');
            $table->text('justification')->nullable();
            $table->foreignId('review_cycle_id')->nullable()->constrained('performance_review_cycles')->nullOnDelete();
            $table->date('effective_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('nominated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('compensation_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('grade', 50)->nullable();
            $table->decimal('min_salary', 14, 2);
            $table->decimal('mid_salary', 14, 2)->nullable();
            $table->decimal('max_salary', 14, 2);
            $table->string('currency', 3)->default('INR');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('compensation_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_cycle_id')->nullable()->constrained('performance_review_cycles')->nullOnDelete();
            $table->foreignId('band_id')->nullable()->constrained('compensation_bands')->nullOnDelete();
            $table->decimal('current_salary', 14, 2)->nullable();
            $table->decimal('recommended_increase_percent', 5, 2)->nullable();
            $table->decimal('recommended_increase_amount', 14, 2)->nullable();
            $table->decimal('recommended_new_salary', 14, 2)->nullable();
            $table->decimal('merit_rating', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_level')->default(5);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('employee_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('current_level')->default(1);
            $table->unsignedTinyInteger('target_level')->default(3);
            $table->text('notes')->nullable();
            $table->date('assessed_at')->nullable();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_competencies');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('compensation_recommendations');
        Schema::dropIfExists('compensation_bands');
        Schema::dropIfExists('promotion_nominations');
        Schema::dropIfExists('performance_calibration_entries');
        Schema::dropIfExists('performance_calibration_sessions');
    }
};
