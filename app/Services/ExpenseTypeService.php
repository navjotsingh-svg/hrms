<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Support\Collection;

class ExpenseTypeService
{
    private const DEFAULT_TYPES = [
        'Travel',
        'Meals',
        'Lodging',
        'Office Supplies',
        'Internet / Phone',
        'Other',
    ];

    public function listForCompany(int $companyId): Collection
    {
        $this->ensureDefaults($companyId);

        return ExpenseType::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function optionsForUser(User $user): Collection
    {
        return $this->listForCompany((int) $user->company_id);
    }

    public function ensureDefaults(int $companyId): void
    {
        if (ExpenseType::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::DEFAULT_TYPES as $index => $name) {
            ExpenseType::create([
                'company_id' => $companyId,
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
        }
    }

    public function seedForCompany(Company $company): void
    {
        $this->ensureDefaults((int) $company->id);
    }
}
