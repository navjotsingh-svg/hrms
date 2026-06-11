<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreAssetTypeRequest;
use App\Http\Requests\UpdateAssetTypeRequest;
use App\Http\Resources\AssetTypeResource;
use App\Models\AssetType;
use App\Services\AssetTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private AssetTypeService $assetTypeService) {}

    public function index(Request $request): JsonResponse
    {
        $assetTypes = $this->assetTypeService->listForCompany(
            $request->user()->company_id,
            $request->only(['search', 'status', 'per_page'])
        );

        return $this->success([
            'asset_types' => AssetTypeResource::collection($assetTypes->items()),
            'pagination' => [
                'current_page' => $assetTypes->currentPage(),
                'last_page' => $assetTypes->lastPage(),
                'per_page' => $assetTypes->perPage(),
                'total' => $assetTypes->total(),
                'from' => $assetTypes->firstItem(),
                'to' => $assetTypes->lastItem(),
            ],
        ]);
    }

    public function store(StoreAssetTypeRequest $request): JsonResponse
    {
        $assetType = $this->assetTypeService->create(
            $request->user()->company_id,
            $request->validated()
        );

        return $this->success(
            ['asset_type' => new AssetTypeResource($assetType)],
            'Asset created successfully.',
            201
        );
    }

    public function show(Request $request, AssetType $assetType): JsonResponse
    {
        $this->ensureCompanyAssetType($request, $assetType);

        return $this->success([
            'asset_type' => new AssetTypeResource($assetType),
        ]);
    }

    public function update(UpdateAssetTypeRequest $request, AssetType $assetType): JsonResponse
    {
        $this->ensureCompanyAssetType($request, $assetType);

        $assetType = $this->assetTypeService->update($assetType, $request->validated());

        return $this->success(
            ['asset_type' => new AssetTypeResource($assetType)],
            'Asset updated successfully.'
        );
    }

    public function destroy(Request $request, AssetType $assetType): JsonResponse
    {
        $this->ensureCompanyAssetType($request, $assetType);

        $this->assetTypeService->delete($assetType);

        return $this->success(null, 'Asset deleted successfully.');
    }

    public function updateStatus(Request $request, AssetType $assetType): JsonResponse
    {
        $this->ensureCompanyAssetType($request, $assetType);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $assetType = $this->assetTypeService->update($assetType, $validated);

        return $this->success(
            ['asset_type' => new AssetTypeResource($assetType)],
            'Asset status updated successfully.'
        );
    }

    private function ensureCompanyAssetType(Request $request, AssetType $assetType): void
    {
        if (! $this->assetTypeService->belongsToCompany($assetType, $request->user()->company_id)) {
            abort(404);
        }
    }
}
