<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'role' => $this->whenLoaded('role', fn () => [
                'slug' => $this->role->slug,
                'name' => $this->role->name,
                'scope' => $this->role->scope,
            ]),
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'company' => new CompanyResource($this->whenLoaded('company')),
        ];
    }
}
