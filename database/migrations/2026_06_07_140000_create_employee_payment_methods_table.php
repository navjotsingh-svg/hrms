<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_mode', ['bank_transfer', 'cash', 'cheque']);
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'payment_mode']);
        });

        if (Schema::hasColumn('employee_salaries', 'bank_status')) {
            $salaries = DB::table('employee_salaries')
                ->whereNotNull('bank_status')
                ->whereNotNull('payment_mode')
                ->get();

            foreach ($salaries as $salary) {
                DB::table('employee_payment_methods')->insert([
                    'company_id' => $salary->company_id,
                    'employee_id' => $salary->employee_id,
                    'payment_mode' => $salary->payment_mode,
                    'bank_name' => $salary->bank_name,
                    'account_holder_name' => $salary->account_holder_name,
                    'account_number' => $salary->account_number,
                    'ifsc_code' => $salary->ifsc_code,
                    'status' => $salary->bank_status,
                    'notes' => $salary->bank_review_notes,
                    'submitted_by_user_id' => $salary->bank_submitted_by_user_id,
                    'reviewed_by_user_id' => $salary->bank_reviewed_by_user_id,
                    'submitted_at' => $salary->bank_submitted_at,
                    'reviewed_at' => $salary->bank_reviewed_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
        }
    }

    public function down(): void
    {
        Schema::table('employee_salaries', function (Blueprint $table) {
            $table->enum('bank_status', ['pending', 'approved', 'rejected'])->nullable()->after('ifsc_code');
            $table->foreignId('bank_submitted_by_user_id')->nullable()->after('bank_status')->constrained('users')->nullOnDelete();
            $table->foreignId('bank_reviewed_by_user_id')->nullable()->after('bank_submitted_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('bank_submitted_at')->nullable()->after('bank_reviewed_by_user_id');
            $table->timestamp('bank_reviewed_at')->nullable()->after('bank_submitted_at');
            $table->text('bank_review_notes')->nullable()->after('bank_reviewed_at');
        });

        Schema::dropIfExists('employee_payment_methods');
    }
};
