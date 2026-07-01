<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstAssetType = $this->relationLoaded('items')
            ? $this->items->first()?->assetType
            : null;

        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => $this->resource->statusLabel(),
            'review_notes' => $this->review_notes,
            'reviewed_at_label' => $this->reviewed_at?->format('d M Y, h:i A'),
            'created_at_label' => $this->created_at?->format('d M Y, h:i A'),
            'has_pending_items' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->resource->hasPendingItems(),
            ),
            'assets_label' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->resource->assetNamesLabel(),
            ),
            'items' => AssetRequestItemResource::collection($this->whenLoaded('items')),
            'asset_types' => $this->when($this->relationLoaded('items'), fn () => $this->items
                ->map(fn ($item) => [
                    'id' => $item->assetType?->id,
                    'name' => $item->assetType?->name,
                ])
                ->filter(fn (array $type) => $type['id'])
                ->values()),
            'asset_type' => $this->when($firstAssetType, fn () => [
                'id' => $firstAssetType->id,
                'name' => $firstAssetType->name,
            ]),
            'employee' => $this->when($this->relationLoaded('employee'), fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ]),
            'applied_by' => $this->when($this->relationLoaded('appliedBy'), fn () => [
                'id' => $this->appliedBy->id,
                'name' => $this->appliedBy->name,
            ]),
            'reviewed_by' => $this->when($this->relationLoaded('reviewedBy') && $this->reviewedBy, fn () => [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ]),
            'can_review' => $request->user()?->canReviewAssetRequest($this->resource) ?? false,
            'can_cancel' => $request->user()?->canCancelAssetRequest($this->resource) ?? false,
        ];
    }
}
