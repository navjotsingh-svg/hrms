<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Department::query()
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

    public function create(int $companyId, array $data): Department
    {
        return Department::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->fresh();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }

    public function belongsToCompany(Department $department, int $companyId): bool
    {
        return (int) $department->company_id === $companyId;
    }
}
