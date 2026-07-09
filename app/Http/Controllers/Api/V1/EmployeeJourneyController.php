<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Models\Employee;
use App\Services\EmployeeJourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeJourneyController extends Controller
{
    use ApiResponse;

    public function __construct(private EmployeeJourneyService $employeeJourneyService) {}

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', 'string', 'max:30'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->success(
            $this->employeeJourneyService->forEmployee(
                $request->user(),
                $employee,
                $validated,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 10),
            )
        );
    }
}
