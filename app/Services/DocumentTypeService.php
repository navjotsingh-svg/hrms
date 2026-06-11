<?php

namespace App\Services;

use App\Models\DocumentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DocumentTypeService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = DocumentType::query()
            ->where('company_id', $companyId)
            ->latest();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(int $companyId, array $data): DocumentType
    {
        return DocumentType::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function update(DocumentType $documentType, array $data): DocumentType
    {
        $documentType->update($data);

        return $documentType->fresh();
    }

    public function delete(DocumentType $documentType): void
    {
        $documentType->delete();
    }

    public function belongsToCompany(DocumentType $documentType, int $companyId): bool
    {
        return (int) $documentType->company_id === $companyId;
    }
}
