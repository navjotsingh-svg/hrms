<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->enum('bank_status', ['pending', 'approved', 'rejected'])->nullable()->after('ifsc_code');
            $table->foreignId('bank_submitted_by_user_id')->nullable()->after('bank_status')->constrained('users')->nullOnDelete();
            $table->foreignId('bank_reviewed_by_user_id')->nullable()->after('bank_submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('bank_submitted_at')->nullable()->after('bank_reviewed_by_user_id');
            $table->timestamp('bank_reviewed_at')->nullable()->after('bank_submitted_at');
            $table->text('bank_review_notes')->nullable()->after('bank_reviewed_at');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->enum('compliance_status', ['pending', 'approved', 'rejected'])->nullable()->after('esi_number');
            $table->foreignId('compliance_submitted_by_user_id')->nullable()->after('compliance_status')->constrained('users')->nullOnDelete();
            $table->foreignId('compliance_reviewed_by_user_id')->nullable()->after('compliance_submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('compliance_submitted_at')->nullable()->after('compliance_reviewed_by_user_id');
            $table->timestamp('compliance_reviewed_at')->nullable()->after('compliance_submitted_at');
            $table->text('compliance_review_notes')->nullable()->after('compliance_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_submitted_by_user_id');
            $table->dropConstrainedForeignId('bank_reviewed_by_user_id');
            $table->dropColumn([
                'bank_status',
                'bank_submitted_at',
                'bank_reviewed_at',
                'bank_review_notes',
            ]);
        });

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
};
