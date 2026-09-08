<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private DashboardService $dashboardService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->dashboardService->forUser($request->user()));
    }
}
