<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('careers_page_settings')) {
            Schema::create('careers_page_settings', function (Blueprint $table) {
                $table->foreignId('company_id')->primary()->constrained()->cascadeOnDelete();
                $table->string('hero_title')->nullable();
                $table->text('hero_subtitle')->nullable();
                $table->text('about_html')->nullable();
                $table->text('header_html')->nullable();
                $table->text('footer_html')->nullable();
                $table->string('banner_path')->nullable();
                $table->string('logo_path')->nullable();
                $table->boolean('is_published')->default(false);
                $table->text('embed_snippet')->nullable();
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hiring_templates')) {
            Schema::create('hiring_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('type', 30)->default('offer_letter');
                $table->text('description')->nullable();
                $table->longText('body_html')->nullable();
                $table->string('file_path')->nullable();
                $table->boolean('is_default')->default(false);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['company_id', 'type']);
            });
        }

        if (! Schema::hasTable('job_postings')) {
            Schema::create('job_postings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('requisition_id')->nullable();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('hiring_manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('title');
                $table->string('slug');
                $table->longText('description_html')->nullable();
                $table->string('location')->nullable();
                $table->string('employment_type', 30)->nullable();
                $table->unsignedSmallInteger('experience_min')->nullable();
                $table->decimal('salary_min', 12, 2)->nullable();
                $table->decimal('salary_max', 12, 2)->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['company_id', 'slug']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('job_requisitions')) {
            Schema::create('job_requisitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('headcount')->default(1);
                $table->string('employment_type', 30)->nullable();
                $table->decimal('budget_min', 12, 2)->nullable();
                $table->decimal('budget_max', 12, 2)->nullable();
                $table->string('urgency', 20)->default('normal');
                $table->string('status', 20)->default('draft');
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('job_id')->nullable()->constrained('job_postings')->nullOnDelete();
                $table->timestamps();
                $table->index(['company_id', 'status']);
            });
        }

        if (Schema::hasTable('job_postings') && Schema::hasTable('job_requisitions')) {
            $this->addRequisitionForeignKey();
        }

        if (! Schema::hasTable('candidates')) {
            Schema::create('candidates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->nullable()->constrained('job_postings')->nullOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email');
                $table->string('phone', 30)->nullable();
                $table->string('resume_path')->nullable();
                $table->string('source', 30)->default('manual');
                $table->string('stage', 30)->default('applied');
                $table->foreignId('assigned_recruiter_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('hired_at')->nullable();
                $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
                $table->index(['company_id', 'stage']);
                $table->index(['job_id', 'stage']);
            });
        }

        if (! Schema::hasTable('candidate_stage_logs')) {
            Schema::create('candidate_stage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
                $table->string('from_stage', 30)->nullable();
                $table->string('to_stage', 30);
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('candidate_interviews')) {
            Schema::create('candidate_interviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->nullable()->constrained('job_postings')->nullOnDelete();
                $table->string('title');
                $table->timestamp('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->string('location')->nullable();
                $table->string('meeting_link')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('scheduled');
                $table->json('panel_user_ids')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hiring_offers')) {
            Schema::create('hiring_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_id')->nullable()->constrained('job_postings')->nullOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('hiring_templates')->nullOnDelete();
                $table->string('title');
                $table->decimal('offered_ctc', 12, 2)->nullable();
                $table->date('joining_date')->nullable();
                $table->longText('letter_html')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_offers');
        Schema::dropIfExists('candidate_interviews');
        Schema::dropIfExists('candidate_stage_logs');
        Schema::dropIfExists('candidates');
        if (Schema::hasTable('job_postings')) {
            Schema::table('job_postings', function (Blueprint $table) {
                if ($this->foreignKeyExists('job_postings', 'job_postings_requisition_id_foreign')) {
                    $table->dropForeign(['requisition_id']);
                }
            });
        }
        Schema::dropIfExists('job_requisitions');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('hiring_templates');
        Schema::dropIfExists('careers_page_settings');
    }

    private function addRequisitionForeignKey(): void
    {
        if ($this->foreignKeyExists('job_postings', 'job_postings_requisition_id_foreign')) {
            return;
        }

        Schema::table('job_postings', function (Blueprint $table) {
            $table->foreign('requisition_id')->references('id')->on('job_requisitions')->nullOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $foreignKey, 'FOREIGN KEY']
        );

        return count($result) > 0;
    }
};
