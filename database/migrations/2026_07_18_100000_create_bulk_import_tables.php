<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 50)->default('employee');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('status', 30)->default('mapping');
            $table->json('headers')->nullable();
            $table->json('column_mapping')->nullable();
            $table->json('preview_rows')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('summary_message')->nullable();
            $table->timestamps();
        });

        Schema::create('bulk_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['bulk_import_id', 'row_number']);
        });

        Schema::create('bulk_import_row_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_import_row_id')->constrained()->cascadeOnDelete();
            $table->string('column_name');
            $table->text('column_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_import_row_extras');
        Schema::dropIfExists('bulk_import_rows');
        Schema::dropIfExists('bulk_imports');
    }
};
