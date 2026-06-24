<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\HomeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private HomeDashboardService $homeDashboardService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'available_widgets' => $this->homeDashboardService->availableWidgets($user),
            'widgets' => $this->homeDashboardService->widgetsWithData($user),
        ]);
    }

    public function syncWidgets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widgets' => ['required', 'array', 'min:1'],
            'widgets.*' => ['required', 'string', 'max:100'],
        ]);

        $widgets = $this->homeDashboardService->syncWidgets($request->user(), $validated['widgets']);

        return $this->success([
            'widgets' => collect($widgets)
                ->map(function (array $widget) use ($request) {
                    $widget['data'] = $this->homeDashboardService->chartData($request->user(), $widget['key']);

                    return $widget;
                })
                ->all(),
        ], 'Dashboard widgets updated.');
    }
}
