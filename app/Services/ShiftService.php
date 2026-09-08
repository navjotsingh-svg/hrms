<?php

namespace App\Services;

use App\Models\Shift;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShiftService
{
    public function listForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = Shift::query()
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

    public function create(int $companyId, array $data): Shift
    {
        return Shift::create([
            ...$this->normalizeShiftData($data),
            'company_id' => $companyId,
        ]);
    }

    public function update(Shift $shift, array $data): Shift
    {
        $shift->update($this->normalizeShiftData($data));

        return $shift->fresh();
    }

    public function delete(Shift $shift): void
    {
        $shift->delete();
    }

    public function belongsToCompany(Shift $shift, int $companyId): bool
    {
        return (int) $shift->company_id === $companyId;
    }

    private function normalizeShiftData(array $data): array
    {
        $data['break_duration_minutes'] = (int) ($data['break_duration_minutes'] ?? 0);
        $data['is_overnight'] = array_key_exists('is_overnight', $data)
            ? (bool) $data['is_overnight']
            : $this->detectOvernight($data['start_time'], $data['end_time']);

        return $data;
    }

    private function detectOvernight(string $startTime, string $endTime): bool
    {
        return strtotime($endTime) <= strtotime($startTime);
    }
}
