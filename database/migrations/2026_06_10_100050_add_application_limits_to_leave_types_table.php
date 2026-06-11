<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $this->addColumnIfMissing('max_days_per_request', function (Blueprint $table) {
            $table->decimal('max_days_per_request', 5, 1)->nullable()->after('annual_quota');
        });

        $this->addColumnIfMissing('max_days_per_month', function (Blueprint $table) {
            $table->decimal('max_days_per_month', 5, 1)->nullable()->after('max_days_per_request');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        Schema::table('leave_types', function (Blueprint $table) {
            $columns = array_values(array_filter([
                $this->columnExists('max_days_per_month') ? 'max_days_per_month' : null,
                $this->columnExists('max_days_per_request') ? 'max_days_per_request' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if ($this->columnExists($column)) {
            return;
        }

        try {
            Schema::table('leave_types', $definition);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (! $this->isDuplicateColumnError($exception)) {
                throw $exception;
            }
        }
    }

    private function columnExists(string $column): bool
    {
        $database = DB::connection()->getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$database, 'leave_types', $column]
        );

        return (int) ($result->total ?? 0) > 0;
    }

    private function isDuplicateColumnError(\Illuminate\Database\QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Duplicate column name')
            || str_contains($message, '1060');
    }
};
