<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExitAssetReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_name' => $this->asset_name,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'condition_notes' => $this->condition_notes,
            'returned_at_label' => $this->returned_at?->labelStack(),
            'received_by' => $this->when($this->relationLoaded('receivedBy') && $this->receivedBy, fn () => [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ]),
            'can_manage' => ($request->user()?->canManageOffboarding() ?? false) && $this->isPending(),
        ];
    }
}
