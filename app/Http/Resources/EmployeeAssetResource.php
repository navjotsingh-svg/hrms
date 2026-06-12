<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'asset_type_id' => $this['asset_type_id'],
            'name' => $this['name'],
            'sort_order' => $this['sort_order'],
            'is_assigned' => (bool) $this['is_assigned'],
            'description' => $this['description'] ?? null,
            'status_label' => $this['is_assigned'] ? 'Available' : 'Not Available',
        ];
    }
}
