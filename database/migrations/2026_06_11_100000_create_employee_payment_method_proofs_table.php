<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_payment_method_proofs')) {
            $this->ensureIndex();

            return;
        }

        Schema::create('employee_payment_method_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_payment_method_id');
            $table->foreign('employee_payment_method_id', 'epm_proofs_method_fk')
                ->references('id')
                ->on('employee_payment_methods')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index(['employee_payment_method_id', 'created_at'], 'epm_proofs_method_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payment_method_proofs');
    }

    private function ensureIndex(): void
    {
        if ($this->indexExists('employee_payment_method_proofs', 'epm_proofs_method_created_idx')) {
            return;
        }

        Schema::table('employee_payment_method_proofs', function (Blueprint $table) {
            $table->index(['employee_payment_method_id', 'created_at'], 'epm_proofs_method_created_idx');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }
};
