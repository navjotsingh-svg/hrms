<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\HiringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HiringOverviewController extends Controller
{
    use ApiResponse;

    public function __construct(private HiringService $hiringService) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success([
            'overview' => $this->hiringService->overviewForUser($request->user()),
        ]);
    }
}
