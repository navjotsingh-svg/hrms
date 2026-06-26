<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    use ApiResponse;

    public function __construct(private HolidayService $holidayService) {}

    public function index(Request $request): JsonResponse
    {
        $canManage = $request->user()->canManageAttendanceMasters();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $canManage) {
            $validated['status'] = 'active';
        }

        $holidays = $this->holidayService->listForCompany(
            $request->user()->company_id,
            $validated
        );

        return $this->success([
            'holidays' => HolidayResource::collection($holidays->items()),
            'pagination' => [
                'current_page' => $holidays->currentPage(),
                'last_page' => $holidays->lastPage(),
                'per_page' => $holidays->perPage(),
                'total' => $holidays->total(),
                'from' => $holidays->firstItem(),
                'to' => $holidays->lastItem(),
            ],
        ]);
    }

    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = $this->holidayService->create(
            $request->user()->company_id,
            $request->validated()
        );

        return $this->success(
            ['holiday' => new HolidayResource($holiday)],
            'Holiday created successfully.',
            201
        );
    }

    public function show(Request $request, Holiday $holiday): JsonResponse
    {
        $this->ensureCompanyHoliday($request, $holiday);

        if (! $request->user()->canManageAttendanceMasters() && $holiday->status !== 'active') {
            abort(404);
        }

        return $this->success(['holiday' => new HolidayResource($holiday)]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        $this->ensureCompanyHoliday($request, $holiday);

        $holiday = $this->holidayService->update($holiday, $request->validated());

        return $this->success(
            ['holiday' => new HolidayResource($holiday)],
            'Holiday updated successfully.'
        );
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        $this->ensureCompanyHoliday($request, $holiday);
        $this->holidayService->delete($holiday);

        return $this->success(null, 'Holiday deleted successfully.');
    }

    private function ensureCompanyHoliday(Request $request, Holiday $holiday): void
    {
        if (! $this->holidayService->belongsToCompany($holiday, $request->user()->company_id)) {
            abort(404);
        }
    }
}
