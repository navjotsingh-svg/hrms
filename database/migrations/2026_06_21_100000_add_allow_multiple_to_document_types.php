<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (! Schema::hasColumn('document_types', 'allow_multiple')) {
                $table->boolean('allow_multiple')->default(false)->after('is_required');
            }
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->index(
                ['employee_id', 'document_type_id'],
                'employee_documents_employee_document_type_index',
            );
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->unique(['employee_id', 'document_type_id']);
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropIndex('employee_documents_employee_document_type_index');
        });

        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'allow_multiple')) {
                $table->dropColumn('allow_multiple');
            }
        });
    }
};
