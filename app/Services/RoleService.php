<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RoleService
{
    /** @return array<int, array<string, mixed>> */
    public function permissionCatalog(): array
    {
        return config('hrms.permission_catalog', []);
    }

    /** @return array<int, string> */
    public function assignablePermissionSlugs(): array
    {
        return collect($this->permissionCatalog())
            ->flatMap(fn (array $group) => collect($group['modules'] ?? [])
                ->flatMap(fn (array $module) => collect($module['operations'] ?? [])
                    ->pluck('slug')))
            ->unique()
            ->values()
            ->all();
    }

    public function listForCompany(int $companyId): Collection
    {
        return Role::query()
            ->where('scope', 'company')
            ->where(function ($query) use ($companyId) {
                $query
                    ->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->with('permissions')
            ->withCount(['users' => fn ($query) => $query->where('company_id', $companyId)])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($companyId) {
                $role->setAttribute(
                    'effective_permissions_count',
                    count($this->permissionSlugsForCompanyRole($companyId, $role)),
                );
                $role->setAttribute(
                    'uses_company_override',
                    $this->usesCompanyOverride($companyId, $role),
                );
                $role->setAttribute('is_custom', $this->isCustomRole($role));
                $role->setAttribute('is_deletable', $this->canDeleteCustomRole($companyId, $role));

                return $role;
            });
    }

    public function findForCompany(int $companyId, Role $role): Role
    {
        $this->ensureAccessible($companyId, $role);

        $role->load('permissions');
        $role->setAttribute('uses_company_override', $this->usesCompanyOverride($companyId, $role));
        $role->setAttribute('effective_permission_slugs', $this->permissionSlugsForCompanyRole($companyId, $role));
        $role->setAttribute('is_editable', $this->isEditable($role));
        $role->setAttribute('is_custom', $this->isCustomRole($role));
        $role->setAttribute('is_deletable', $this->canDeleteCustomRole($companyId, $role));
        $role->setAttribute(
            'users_count',
            $role->users()->where('company_id', $companyId)->count(),
        );

        return $role;
    }

    /** @return array<string, mixed> */
    public function showPayload(int $companyId, Role $role): array
    {
        $role = $this->findForCompany($companyId, $role);

        return [
            'role' => $role,
            'permission_catalog' => $this->permissionCatalog(),
            'effective_permission_slugs' => $role->getAttribute('effective_permission_slugs'),
            'uses_company_override' => $role->getAttribute('uses_company_override'),
            'is_editable' => $role->getAttribute('is_editable'),
        ];
    }

    public function usesCompanyOverride(int $companyId, Role $role): bool
    {
        return DB::table('company_role_permissions')
            ->where('company_id', $companyId)
            ->where('role_id', $role->id)
            ->exists();
    }

    /** @return array<int, string> */
    public function permissionSlugsForCompanyRole(int $companyId, Role $role): array
    {
        $overrideSlugs = DB::table('company_role_permissions')
            ->where('company_id', $companyId)
            ->where('role_id', $role->id)
            ->join('permissions', 'permissions.id', '=', 'company_role_permissions.permission_id')
            ->pluck('permissions.slug')
            ->all();

        if ($overrideSlugs !== []) {
            return array_values(array_unique($overrideSlugs));
        }

        return $role->permissions()->pluck('slug')->all();
    }

    public function userHasPermission(User $user, string $slug): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $role = $user->role;

        if (! $role || ! $user->company_id) {
            return false;
        }

        return in_array($slug, $this->permissionSlugsForCompanyRole((int) $user->company_id, $role), true);
    }

    /** @param  array<int, string>  $permissionSlugs */
    public function syncPermissionsForCompany(int $companyId, Role $role, array $permissionSlugs): Role
    {
        $this->ensureAccessible($companyId, $role);

        if (! $this->isEditable($role)) {
            throw new AccessDeniedHttpException('This role cannot be modified.');
        }

        $permissionSlugs = $this->normalizePermissionSlugs($permissionSlugs);
        $permissionIds = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->pluck('id');

        DB::transaction(function () use ($companyId, $role, $permissionIds) {
            DB::table('company_role_permissions')
                ->where('company_id', $companyId)
                ->where('role_id', $role->id)
                ->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('company_role_permissions')->insert([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        });

        return $this->findForCompany($companyId, $role);
    }

    public function resetPermissionsForCompany(int $companyId, Role $role): Role
    {
        $this->ensureAccessible($companyId, $role);

        if (! $this->isEditable($role)) {
            throw new AccessDeniedHttpException('This role cannot be modified.');
        }

        DB::table('company_role_permissions')
            ->where('company_id', $companyId)
            ->where('role_id', $role->id)
            ->delete();

        return $this->findForCompany($companyId, $role);
    }

    /** @param  array<string, mixed>  $data */
    public function createCustomRole(int $companyId, array $data): Role
    {
        $permissionSlugs = $this->normalizePermissionSlugs($data['permission_slugs'] ?? []);
        $slug = $this->uniqueCustomSlug($companyId, (string) $data['name']);

        $role = Role::create([
            'company_id' => $companyId,
            'slug' => $slug,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope' => 'company',
            'is_system' => false,
            'status' => $data['status'] ?? 'active',
        ]);

        if ($permissionSlugs !== []) {
            $this->syncPermissionsForCompany($companyId, $role, $permissionSlugs);
        }

        return $this->findForCompany($companyId, $role);
    }

    /** @param  array<string, mixed>  $data */
    public function updateCustomRole(int $companyId, Role $role, array $data): Role
    {
        $this->ensureAccessible($companyId, $role);

        if ($role->is_system) {
            throw new AccessDeniedHttpException('System roles can only have permissions updated.');
        }

        if ((int) $role->company_id !== $companyId) {
            throw new NotFoundHttpException('Role not found.');
        }

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => $data['description'] ?? $role->description,
            'status' => $data['status'] ?? $role->status,
        ]);

        if (array_key_exists('permission_slugs', $data)) {
            $this->syncPermissionsForCompany(
                $companyId,
                $role,
                $this->normalizePermissionSlugs($data['permission_slugs'] ?? []),
            );
        }

        return $this->findForCompany($companyId, $role);
    }

    public function deleteCustomRole(int $companyId, Role $role): void
    {
        $this->ensureAccessible($companyId, $role);

        if ($role->is_system || $role->company_id === null) {
            throw new AccessDeniedHttpException('System roles cannot be deleted.');
        }

        if ((int) $role->company_id !== $companyId) {
            throw new NotFoundHttpException('Role not found.');
        }

        if ($role->users()->where('company_id', $companyId)->exists()) {
            throw new AccessDeniedHttpException('Remove all users from this role before deleting it.');
        }

        DB::table('company_role_permissions')
            ->where('company_id', $companyId)
            ->where('role_id', $role->id)
            ->delete();

        $role->permissions()->detach();
        $role->delete();
    }

    public function isEditable(Role $role): bool
    {
        return ! in_array($role->slug, [Role::SLUG_SUPER_ADMIN, Role::SLUG_COMPANY_ADMIN], true);
    }

    public function isCustomRole(Role $role): bool
    {
        return ! $role->is_system && $role->company_id !== null;
    }

    public function canDeleteCustomRole(int $companyId, Role $role): bool
    {
        if (! $this->isCustomRole($role) || (int) $role->company_id !== $companyId) {
            return false;
        }

        return ! $role->users()->where('company_id', $companyId)->exists();
    }

    public function ensureAccessible(int $companyId, Role $role): void
    {
        if ($role->scope !== 'company') {
            throw new NotFoundHttpException('Role not found.');
        }

        if ($role->company_id !== null && (int) $role->company_id !== $companyId) {
            throw new NotFoundHttpException('Role not found.');
        }
    }

    /** @param  array<int, string>  $permissionSlugs */
    private function normalizePermissionSlugs(array $permissionSlugs): array
    {
        $allowed = $this->assignablePermissionSlugs();
        $selected = collect($permissionSlugs)
            ->filter(fn ($slug) => is_string($slug) && in_array($slug, $allowed, true))
            ->unique()
            ->values();

        foreach ($this->permissionCatalog() as $group) {
            foreach ($group['modules'] ?? [] as $module) {
                foreach ($module['operations'] ?? [] as $operation) {
                    $slug = $operation['slug'] ?? null;
                    $requires = $operation['requires'] ?? [];

                    if (! $slug || $requires === [] || ! $selected->contains($slug)) {
                        continue;
                    }

                    foreach ($requires as $requiredSlug) {
                        if (! $selected->contains($requiredSlug)) {
                            $selected->push($requiredSlug);
                        }
                    }
                }
            }
        }

        return $selected->unique()->values()->all();
    }

    private function uniqueCustomSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'custom-role';
        }

        $slug = $base;
        $suffix = 1;

        while (Role::query()
            ->where('company_id', $companyId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
