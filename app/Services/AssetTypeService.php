<?php

namespace App\Services;

use App\Models\AssetType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetTypeService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = AssetType::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where('name', 'like', "%{$search}%");
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function activeForCompany(int $companyId)
    {
        return AssetType::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function create(int $companyId, array $data): AssetType
    {
        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            $data['sort_order'] = (int) AssetType::query()
                ->where('company_id', $companyId)
                ->max('sort_order') + 1;
        }

        return AssetType::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function update(AssetType $assetType, array $data): AssetType
    {
        $assetType->update($data);

        return $assetType->fresh();
    }

    public function delete(AssetType $assetType): void
    {
        $assetType->delete();
    }

    public function belongsToCompany(AssetType $assetType, int $companyId): bool
    {
        return (int) $assetType->company_id === $companyId;
    }
}
