<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::query()->where('slug', Role::SLUG_SUPER_ADMIN)->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@hrms.com'],
            [
                'company_id' => null,
                'role_id' => $superAdminRole->id,
                'name' => 'Admin',
                'password' => 'Admin@123',
                'email_verified_at' => now(),
            ]
        );
    }
}
