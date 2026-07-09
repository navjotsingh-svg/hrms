<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyAdminEmployeeService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(CompanyAdminEmployeeService::class);

        User::query()
            ->whereNotNull('company_id')
            ->whereHas('role', fn ($query) => $query->where('slug', Role::SLUG_COMPANY_ADMIN))
            ->orderBy('id')
            ->each(fn (User $user) => $service->ensureForAdmin($user));

        Company::query()
            ->whereHas('adminUser')
            ->with('adminUser')
            ->orderBy('id')
            ->each(function (Company $company) use ($service) {
                if ($company->adminUser) {
                    $service->ensureForAdmin($company->adminUser);
                }
            });
    }

    public function down(): void
    {
        // Employee records created for admins are retained.
    }
};
