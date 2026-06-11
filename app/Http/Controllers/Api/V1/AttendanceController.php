<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreAttendancePunchRequest;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(private AttendanceService $attendanceService) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            ...$this->attendanceService->todayStatus($user),
            'capabilities' => $this->capabilities($user),
        ]);
    }

    public function punch(StoreAttendancePunchRequest $request): JsonResponse
    {
        $result = $this->attendanceService->punch(
            $request->user(),
            $request->file('selfie'),
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            $request->input('location_name'),
        );

        $label = $result['punch']['punch_type'] === 'in' ? 'Punch in' : 'Punch out';

        return $this->success($result, "{$label} recorded successfully.", 201);
    }

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        return $this->success([
            ...$this->attendanceService->calendar(
                $request->user(),
                $validated['month'],
                $validated['employee_id'] ?? null,
            ),
            'capabilities' => $this->capabilities($request->user()),
        ]);
    }

    public function day(Request $request, string $date): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        return $this->success(
            $this->attendanceService->dayDetail(
                $request->user(),
                $date,
                $validated['employee_id'] ?? null,
            )
        );
    }

    public function todayOverview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return $this->success(
            $this->attendanceService->todayOverview(
                $request->user(),
                $validated['date'] ?? null,
            )
        );
    }

    private function capabilities($user): array
    {
        $canViewAll = $this->attendanceService->canViewAllAttendance($user);
        $canViewTeam = ! $canViewAll && $this->attendanceService->canViewTeamAttendance($user);

        return [
            'can_mark' => $this->attendanceService->canMarkAttendance($user),
            'can_view_all' => $canViewAll,
            'can_view_team' => $canViewTeam,
            'team_employees' => $canViewTeam ? $this->attendanceService->teamEmployeesForUser($user) : [],
            'self_employee_id' => $user->employee?->id,
            'default_view_own' => $user->isHrManager() && ! $user->isCompanyAdmin(),
        ];
    }
}
