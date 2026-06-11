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
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'scope' => $this->scope,
            'level' => $this->level,
            'status' => $this->status,
            'is_system' => $this->is_system,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissions_count' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->permissions->count()
            ),
            'users_count' => $this->when(isset($this->users_count), $this->users_count),
        ];
    }
}
