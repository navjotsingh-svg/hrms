<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
<<<<<<< HEAD
        if (! Schema::hasTable('companies') || Schema::hasColumn('companies', 'attendance_regularization_enabled')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            $column = $table->boolean('attendance_regularization_enabled')->nullable();

            if (Schema::hasColumn('companies', 'attendance_regularization_block_current_day_until_complete')) {
                $column->after('attendance_regularization_block_current_day_until_complete');
            }
=======
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('attendance_regularization_enabled')
                ->nullable()
                ->after('attendance_regularization_block_current_day_until_complete');
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
        });
    }

    public function down(): void
    {
<<<<<<< HEAD
        if (! Schema::hasTable('companies') || ! Schema::hasColumn('companies', 'attendance_regularization_enabled')) {
            return;
        }

=======
>>>>>>> 7c33f59688f786601028b5d68f2b07f2351bf8b9
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('attendance_regularization_enabled');
        });
    }
};
