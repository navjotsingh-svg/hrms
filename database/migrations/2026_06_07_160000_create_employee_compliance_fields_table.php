<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compliance_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('field_type', ['pan', 'aadhaar', 'uan', 'pf', 'esi']);
            $table->string('value');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'field_type']);
        });

        if (Schema::hasColumn('employees', 'compliance_status')) {
            $employees = DB::table('employees')->whereNotNull('compliance_status')->get();
            $fieldMap = [
                'pan' => 'pan_number',
                'aadhaar' => 'aadhaar_number',
                'uan' => 'uan',
                'pf' => 'pf_number',
                'esi' => 'esi_number',
            ];

            foreach ($employees as $employee) {
                foreach ($fieldMap as $type => $column) {
                    if (empty($employee->{$column})) {
                        continue;
                    }

                    DB::table('employee_compliance_fields')->insert([
                        'company_id' => $employee->company_id,
                        'employee_id' => $employee->id,
                        'field_type' => $type,
                        'value' => $employee->{$column},
                        'status' => $employee->compliance_status,
                        'notes' => $employee->compliance_review_notes,
                        'submitted_by_user_id' => $employee->compliance_submitted_by_user_id,
                        'reviewed_by_user_id' => $employee->compliance_reviewed_by_user_id,
                        'submitted_at' => $employee->compliance_submitted_at,
                        'reviewed_at' => $employee->compliance_reviewed_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('compliance_submitted_by_user_id');
                $table->dropConstrainedForeignId('compliance_reviewed_by_user_id');
                $table->dropColumn([
                    'compliance_status',
                    'compliance_submitted_at',
                    'compliance_reviewed_at',
                    'compliance_review_notes',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('compliance_status', ['pending', 'approved', 'rejected'])->nullable()->after('esi_number');
            $table->foreignId('compliance_submitted_by_user_id')->nullable()->after('compliance_status')->constrained('users')->nullOnDelete();
            $table->foreignId('compliance_reviewed_by_user_id')->nullable()->after('compliance_submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('compliance_submitted_at')->nullable()->after('compliance_reviewed_by_user_id');
            $table->timestamp('compliance_reviewed_at')->nullable()->after('compliance_submitted_at');
            $table->text('compliance_review_notes')->nullable()->after('compliance_reviewed_at');
        });

        Schema::dropIfExists('employee_compliance_fields');
    }
};
