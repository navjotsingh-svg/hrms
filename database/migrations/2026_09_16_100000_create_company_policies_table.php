<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_policies')) {
            Schema::create('company_policies', function (Blueprint $table) {
                $table->id();
                // No DB foreign keys: some environments have companies.id without a usable PK index.
                $table->unsignedBigInteger('company_id');
                $table->string('title');
                $table->string('category', 50);
                $table->text('description')->nullable();
                $table->string('original_name');
                $table->string('file_path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('status', 20)->default('published');
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'company_policies_company_status_index');
                $table->index(['company_id', 'category'], 'company_policies_company_category_index');
                $table->index('uploaded_by_user_id', 'company_policies_uploaded_by_user_id_index');
            });

            return;
        }

        $this->ensureIndex('company_policies_company_status_index', ['company_id', 'status']);
        $this->ensureIndex('company_policies_company_category_index', ['company_id', 'category']);
        $this->ensureIndex('company_policies_uploaded_by_user_id_index', ['uploaded_by_user_id']);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_policies');
    }

    /** @param  array<int, string>  $columns */
    private function ensureIndex(string $name, array $columns): void
    {
        $exists = collect(DB::select('SHOW INDEX FROM company_policies'))
            ->contains(fn ($row) => ($row->Key_name ?? null) === $name);

        if ($exists) {
            return;
        }

        Schema::table('company_policies', function (Blueprint $table) use ($name, $columns) {
            $table->index($columns, $name);
        });
    }
};
