<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(private RoleService $roleService) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->roleService->listForCompany((int) $request->user()->company_id);

        return $this->success([
            'roles' => RoleResource::collection($roles),
        ]);
    }

    public function permissionCatalog(Request $request): JsonResponse
    {
        return $this->success([
            'catalog' => $this->roleService->permissionCatalog(),
            'assignable_slugs' => $this->roleService->assignablePermissionSlugs(),
        ]);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $payload = $this->roleService->showPayload((int) $request->user()->company_id, $role);

        return $this->success([
            'role' => new RoleResource($payload['role']),
            'permission_catalog' => $payload['permission_catalog'],
            'effective_permission_slugs' => $payload['effective_permission_slugs'],
            'uses_company_override' => $payload['uses_company_override'],
            'is_editable' => $payload['is_editable'],
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->createCustomRole(
            (int) $request->user()->company_id,
            $request->validated(),
        );

        return $this->success(
            ['role' => new RoleResource($role)],
            'Role created successfully.',
            201,
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->updateCustomRole(
            (int) $request->user()->company_id,
            $role,
            $request->validated(),
        );

        return $this->success(
            ['role' => new RoleResource($role)],
            'Role updated successfully.',
        );
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->syncPermissionsForCompany(
            (int) $request->user()->company_id,
            $role,
            $request->validated('permission_slugs'),
        );

        return $this->success(
            ['role' => new RoleResource($role)],
            'Role permissions updated successfully.',
        );
    }

    public function resetPermissions(Request $request, Role $role): JsonResponse
    {
        $role = $this->roleService->resetPermissionsForCompany(
            (int) $request->user()->company_id,
            $role,
        );

        return $this->success(
            ['role' => new RoleResource($role)],
            'Role permissions reset to system defaults.',
        );
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->roleService->deleteCustomRole((int) $request->user()->company_id, $role);

        return $this->success(null, 'Role deleted successfully.');
    }
}
