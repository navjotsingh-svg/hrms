<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HolidayService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Holiday::query()
            ->where('company_id', $companyId)
            ->orderByDesc('date');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($year = $filters['year'] ?? null) {
            $query->whereYear('date', $year);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 10);
    }

    public function create(int $companyId, array $data): Holiday
    {
        return Holiday::create([
            ...$data,
            'company_id' => $companyId,
        ]);
    }

    public function update(Holiday $holiday, array $data): Holiday
    {
        $holiday->update($data);

        return $holiday->fresh();
    }

    public function delete(Holiday $holiday): void
    {
        $holiday->delete();
    }

    public function belongsToCompany(Holiday $holiday, int $companyId): bool
    {
        return (int) $holiday->company_id === $companyId;
    }
}
