<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeAssetService
{
    public function __construct(private AssetTypeService $assetTypeService) {}

    public function assignmentsForEmployee(Employee $employee): Collection
    {
        $assetTypes = $this->assetTypeService->activeForCompany($employee->company_id);
        $assigned = $employee->relationLoaded('employeeAssets')
            ? $employee->employeeAssets->keyBy('asset_type_id')
            : EmployeeAsset::query()
                ->where('employee_id', $employee->id)
                ->get()
                ->keyBy('asset_type_id');

        return $assetTypes->map(function ($assetType) use ($assigned) {
            $record = $assigned->get($assetType->id);

            return [
                'asset_type_id' => $assetType->id,
                'name' => $assetType->name,
                'sort_order' => $assetType->sort_order,
                'is_assigned' => (bool) ($record?->is_assigned),
                'description' => $record?->is_assigned ? $record?->description : null,
            ];
        });
    }

    public function syncAssignments(Employee $employee, array $assets): Collection
    {
        $assetTypeIds = $this->assetTypeService
            ->activeForCompany($employee->company_id)
            ->pluck('id')
            ->all();

        $payload = collect($assets)
            ->filter(fn (array $item) => in_array((int) ($item['asset_type_id'] ?? 0), $assetTypeIds, true))
            ->keyBy('asset_type_id');

        DB::transaction(function () use ($employee, $assetTypeIds, $payload) {
            foreach ($assetTypeIds as $assetTypeId) {
                EmployeeAsset::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'asset_type_id' => $assetTypeId,
                    ],
                    [
                        'is_assigned' => (bool) ($payload->get($assetTypeId)['is_assigned'] ?? false),
                        'description' => ($payload->get($assetTypeId)['is_assigned'] ?? false)
                            ? ($payload->get($assetTypeId)['description'] ?? null)
                            : null,
                    ],
                );
            }
        });

        $employee->load('employeeAssets.assetType');

        return $this->assignmentsForEmployee($employee);
    }

    public function assignAssetType(Employee $employee, int $assetTypeId, ?string $description = null): void
    {
        $belongsToCompany = $this->assetTypeService
            ->activeForCompany($employee->company_id)
            ->contains(fn ($type) => (int) $type->id === $assetTypeId);

        if (! $belongsToCompany) {
            return;
        }

        EmployeeAsset::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'asset_type_id' => $assetTypeId,
            ],
            [
                'is_assigned' => true,
                'description' => $description,
            ],
        );
    }
}
