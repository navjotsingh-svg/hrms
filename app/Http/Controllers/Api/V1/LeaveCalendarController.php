<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Services\LeaveCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveCalendarController extends Controller
{
    use ApiResponse;

    public function __construct(private LeaveCalendarService $calendarService) {}

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'include_holidays' => ['nullable', 'boolean'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);
        $includeHolidays = array_key_exists('include_holidays', $validated)
            ? (bool) $validated['include_holidays']
            : true;

        return $this->success([
            'calendar' => $this->calendarService->calendarForUser(
                $request->user(),
                $year,
                $month,
                $includeHolidays
            ),
        ]);
    }
}
