<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'scope' => $this->scope,
            'status' => $this->status,
            'is_system' => $this->is_system,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissions_count' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->permissions->count()
            ),
            'effective_permissions_count' => $this->when(
                isset($this->effective_permissions_count),
                $this->effective_permissions_count,
            ),
            'effective_permission_slugs' => $this->when(
                isset($this->effective_permission_slugs),
                $this->effective_permission_slugs,
            ),
            'uses_company_override' => $this->when(
                isset($this->uses_company_override),
                (bool) $this->uses_company_override,
            ),
            'is_editable' => $this->when(
                isset($this->is_editable),
                (bool) $this->is_editable,
            ),
            'is_custom' => $this->when(
                isset($this->is_custom),
                (bool) $this->is_custom,
            ),
            'is_deletable' => $this->when(
                isset($this->is_deletable),
                (bool) $this->is_deletable,
            ),
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
        ];
    }
}
