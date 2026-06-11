<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\UpdatePortalStartRequest;
use App\Services\PortalStartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalStartController extends Controller
{
    use ApiResponse;

    public function __construct(private PortalStartService $portalStartService) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success(
            $this->portalStartService->getForCompany($request->user()->company_id)
        );
    }

    public function update(UpdatePortalStartRequest $request): JsonResponse
    {
        $config = $this->portalStartService->updateForCompany(
            $request->user()->company_id,
            $request->validated()['attendance_portal_start_date'] ?? null
        );

        return $this->success($config, 'Portal start day updated successfully.');
    }
}
