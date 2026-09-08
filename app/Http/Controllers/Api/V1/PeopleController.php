<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\PeopleDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    use ApiResponse;

    public function __construct(private PeopleDirectoryService $peopleDirectoryService) {}

    public function summary(Request $request): JsonResponse
    {
        $paginator = $this->peopleDirectoryService->summaryForUser(
            $request->user(),
            $request->only(['search', 'per_page'])
        );

        return $this->success([
            'employees' => collect($paginator->items())
                ->map(fn ($employee) => $this->peopleDirectoryService->mapSummaryRow($employee))
                ->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function orgChart(Request $request): JsonResponse
    {
        return $this->success($this->peopleDirectoryService->orgChartForUser($request->user()));
    }
}
