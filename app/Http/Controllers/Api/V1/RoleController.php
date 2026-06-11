<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $roles = Role::query()
            ->where('scope', 'company')
            ->with('permissions')
            ->withCount(['users' => fn ($query) => $query->where('company_id', $companyId)])
            ->orderByDesc('level')
            ->get();

        return $this->success([
            'roles' => RoleResource::collection($roles),
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        if ($role->scope !== 'company') {
            abort(404);
        }

        $role->load('permissions');

        return $this->success([
            'role' => new RoleResource($role),
        ]);
    }
}
