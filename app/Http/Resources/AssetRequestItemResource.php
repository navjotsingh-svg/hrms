<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetRequestItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'review_notes' => $this->review_notes,
            'reviewed_at_label' => $this->reviewed_at?->labelStack(),
            'asset_type' => $this->when($this->relationLoaded('assetType') && $this->assetType, fn () => [
                'id' => $this->assetType->id,
                'name' => $this->assetType->name,
            ]),
            'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, fn () => [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ]),
            'can_review' => $request->user()?->canReviewAssetRequestItem($this->resource) ?? false,
        ];
    }
}
