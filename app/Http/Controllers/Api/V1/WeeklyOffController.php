<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\UpdateWeeklyOffRequest;
use App\Services\WeeklyOffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeeklyOffController extends Controller
{
    use ApiResponse;

    public function __construct(private WeeklyOffService $weeklyOffService) {}

    public function show(Request $request): JsonResponse
    {
        return $this->success(
            $this->weeklyOffService->getForCompany($request->user()->company_id)
        );
    }

    public function update(UpdateWeeklyOffRequest $request): JsonResponse
    {
        $config = $this->weeklyOffService->syncForCompany(
            $request->user()->company_id,
            $request->validated()['weekdays']
        );

        return $this->success($config, 'Weekly off days updated successfully.');
    }
}
