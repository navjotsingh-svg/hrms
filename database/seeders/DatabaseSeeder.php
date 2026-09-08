<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('personal_access_tokens')->truncate();
        DB::table('sessions')->truncate();
        DB::table('password_reset_tokens')->truncate();
        DB::table('users')->truncate();
        DB::table('companies')->truncate();
        DB::table('role_permission')->truncate();
        DB::table('permissions')->truncate();
        DB::table('roles')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
