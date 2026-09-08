<?php

namespace App\Services;

use App\Models\HelpdeskCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class HelpdeskCategoryService
{
    /** @return Collection<int, HelpdeskCategory> */
    public function activeCategoriesForCompany(int $companyId): Collection
    {
        $this->ensureDefaultsForCompany($companyId);

        return HelpdeskCategory::query()
            ->where('company_id', $companyId)
            ->where('status', HelpdeskCategory::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(User $user, array $data): HelpdeskCategory
    {
        $this->assertCanManage($user);

        $name = trim($data['name']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Category name is required.'],
            ]);
        }

        $exists = HelpdeskCategory::query()
            ->where('company_id', $user->company_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A category with this name already exists.'],
            ]);
        }

        return HelpdeskCategory::query()->create([
            'company_id' => $user->company_id,
            'name' => $name,
            'sort_order' => (int) ($data['sort_order'] ?? $this->nextSortOrder($user->company_id)),
            'status' => $data['status'] ?? HelpdeskCategory::STATUS_ACTIVE,
        ]);
    }

    public function resolveActiveCategory(int $companyId, int $categoryId): HelpdeskCategory
    {
        $this->ensureDefaultsForCompany($companyId);

        $category = HelpdeskCategory::query()
            ->where('company_id', $companyId)
            ->where('id', $categoryId)
            ->where('status', HelpdeskCategory::STATUS_ACTIVE)
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'helpdesk_category_id' => ['Please choose a valid category.'],
            ]);
        }

        return $category;
    }

    public function ensureDefaultsForCompany(int $companyId): void
    {
        if (HelpdeskCategory::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        $sort = 0;

        foreach (config('helpdesk.categories', []) as $name) {
            HelpdeskCategory::query()->create([
                'company_id' => $companyId,
                'name' => $name,
                'sort_order' => $sort++,
                'status' => HelpdeskCategory::STATUS_ACTIVE,
            ]);
        }
    }

    private function nextSortOrder(int $companyId): int
    {
        return ((int) HelpdeskCategory::query()->where('company_id', $companyId)->max('sort_order')) + 1;
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->canManageHelpdesk()) {
            throw new AccessDeniedHttpException('You are not allowed to manage helpdesk categories.');
        }
    }
}
