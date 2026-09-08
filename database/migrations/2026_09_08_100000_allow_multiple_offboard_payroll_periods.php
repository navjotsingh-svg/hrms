<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreAutoIncrementingPrimaryKey('migrations', 'int(10) unsigned');
        $this->restoreAutoIncrementingPrimaryKey('payroll_periods');
        $this->restoreAutoIncrementingPrimaryKey('payslips');
        $this->restoreAutoIncrementingPrimaryKey('exit_cases');
        $this->restoreAutoIncrementingPrimaryKey('full_and_final_settlements');

        if (Schema::hasTable('exit_cases') && ! Schema::hasColumn('exit_cases', 'exit_type')) {
            DB::statement("ALTER TABLE `exit_cases` ADD `exit_type` VARCHAR(30) NOT NULL DEFAULT 'resignation' AFTER `employee_id`");
        }

        if (Schema::hasTable('exit_cases') && Schema::hasColumn('exit_cases', 'resignation_request_id')) {
            DB::statement('ALTER TABLE `exit_cases` MODIFY `resignation_request_id` BIGINT UNSIGNED NULL');
        }

        $this->addUniqueIndexIfMissing('payroll_periods', 'payroll_periods_exit_case_id_unique', ['exit_case_id']);
    }

    public function down(): void
    {
        // Schema repair — keys are required for payroll inserts and should stay.
    }

    private function restoreAutoIncrementingPrimaryKey(string $table, string $idType = 'bigint(20) unsigned'): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $create = DB::selectOne("SHOW CREATE TABLE `{$table}`")->{'Create Table'} ?? '';
        $hasPrimaryKey = str_contains($create, 'PRIMARY KEY');
        $hasAutoIncrement = str_contains($create, 'AUTO_INCREMENT');

        if (! $hasPrimaryKey) {
            $duplicates = DB::select("SELECT `id`, COUNT(*) AS aggregate FROM `{$table}` GROUP BY `id` HAVING aggregate > 1 LIMIT 1");

            if ($duplicates !== []) {
                throw new RuntimeException("Cannot restore primary key on {$table}: duplicate id values exist.");
            }

            DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
        }

        if (! $hasAutoIncrement) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` {$idType} NOT NULL AUTO_INCREMENT");
        }

        $maxId = (int) (DB::table($table)->max('id') ?? 0);
        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = ".($maxId + 1));
    }

    /** @param  array<int, string>  $columns */
    private function addUniqueIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $exists = collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn ($index) => $index->Key_name === $indexName);

        if ($exists) {
            return;
        }

        $columnList = collect($columns)->map(fn ($column) => "`{$column}`")->implode(', ');
        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` ({$columnList})");
    }
};
