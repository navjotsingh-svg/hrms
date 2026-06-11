<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    use ApiResponse;

    public function __construct(private ShiftService $shiftService) {}

    public function index(Request $request): JsonResponse
    {
        $shifts = $this->shiftService->listForCompany(
            $request->user()->company_id,
            $request->only(['search', 'status', 'per_page'])
        );

        return $this->success([
            'shifts' => ShiftResource::collection($shifts->items()),
            'pagination' => [
                'current_page' => $shifts->currentPage(),
                'last_page' => $shifts->lastPage(),
                'per_page' => $shifts->perPage(),
                'total' => $shifts->total(),
                'from' => $shifts->firstItem(),
                'to' => $shifts->lastItem(),
            ],
        ]);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $shift = $this->shiftService->create(
            $request->user()->company_id,
            $request->validated()
        );

        return $this->success(
            ['shift' => new ShiftResource($shift)],
            'Shift created successfully.',
            201
        );
    }

    public function show(Request $request, Shift $shift): JsonResponse
    {
        $this->ensureCompanyShift($request, $shift);

        return $this->success([
            'shift' => new ShiftResource($shift),
        ]);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        $this->ensureCompanyShift($request, $shift);

        $shift = $this->shiftService->update($shift, $request->validated());

        return $this->success(
            ['shift' => new ShiftResource($shift)],
            'Shift updated successfully.'
        );
    }

    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        $this->ensureCompanyShift($request, $shift);

        $this->shiftService->delete($shift);

        return $this->success(null, 'Shift deleted successfully.');
    }

    public function updateStatus(Request $request, Shift $shift): JsonResponse
    {
        $this->ensureCompanyShift($request, $shift);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $shift = $this->shiftService->update($shift, $validated);

        return $this->success(
            ['shift' => new ShiftResource($shift)],
            'Shift status updated successfully.'
        );
    }

    private function ensureCompanyShift(Request $request, Shift $shift): void
    {
        if (! $this->shiftService->belongsToCompany($shift, $request->user()->company_id)) {
            abort(404);
        }
    }
}
