<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRolePermissionsCommand extends Command
{
    protected $signature = 'hrms:sync-role-permissions
                            {--company= : Limit company-role override patching to one company ID}';

    protected $description = 'Sync permission records and system role defaults; patch stale company role overrides';

    public function handle(): int
    {
        $this->info('Syncing permissions and system roles...');
        $this->callSilent('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $companyId = $this->option('company') !== null
            ? (int) $this->option('company')
            : null;

        $patched = $this->patchCompanyRoleOverrides($companyId);

        $this->info("Done. Patched {$patched} company role override row(s) with missing permissions.");

        return self::SUCCESS;
    }

    private function patchCompanyRoleOverrides(?int $companyId): int
    {
        $systemRoles = Role::query()
            ->where('scope', 'company')
            ->where('is_system', true)
            ->with('permissions')
            ->get()
            ->keyBy('id');

        $query = DB::table('company_role_permissions')
            ->select('company_id', 'role_id')
            ->distinct();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $pairs = $query->get();
        $inserted = 0;

        foreach ($pairs as $pair) {
            $role = $systemRoles->get($pair->role_id);

            if (! $role) {
                continue;
            }

            $baselineIds = $role->permissions->pluck('id');
            $existingIds = DB::table('company_role_permissions')
                ->where('company_id', $pair->company_id)
                ->where('role_id', $pair->role_id)
                ->pluck('permission_id');

            foreach ($baselineIds as $permissionId) {
                if ($existingIds->contains($permissionId)) {
                    continue;
                }

                DB::table('company_role_permissions')->insert([
                    'company_id' => $pair->company_id,
                    'role_id' => $pair->role_id,
                    'permission_id' => $permissionId,
                ]);

                $inserted++;
            }
        }

        return $inserted;
    }
}
