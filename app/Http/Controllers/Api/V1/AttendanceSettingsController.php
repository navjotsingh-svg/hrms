<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Concerns\ApiResponse;
use App\Http\Requests\StoreAttendanceFaceReferenceRequest;
use App\Http\Requests\UpdateAttendanceRegularizationSettingsRequest;
use App\Http\Requests\UpdateAttendanceFaceSettingsRequest;
use App\Http\Requests\UpdateAttendanceNetworkSettingsRequest;
use App\Services\AttendanceNetworkService;
use App\Services\AttendanceSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AttendanceSettingsService $attendanceSettingsService,
        private AttendanceNetworkService $attendanceNetworkService,
    ) {}

    public function showNetwork(Request $request): JsonResponse
    {
        return $this->success(
            $this->attendanceSettingsService->networkSettingsForCompany((int) $request->user()->company_id)
        );
    }

    public function updateNetwork(UpdateAttendanceNetworkSettingsRequest $request): JsonResponse
    {
        $settings = $this->attendanceSettingsService->updateNetworkSettingsForCompany(
            (int) $request->user()->company_id,
            $request->input('attendance_allowed_ips'),
        );

        return $this->success($settings, 'Attendance network settings updated successfully.');
    }

    public function updateFace(UpdateAttendanceFaceSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $settings = $this->attendanceSettingsService->updateFaceSettingsForCompany(
            (int) $request->user()->company_id,
            array_key_exists('face_match_threshold', $validated) ? $validated['face_match_threshold'] : null,
            array_key_exists('require_face_match', $validated) ? (bool) $validated['require_face_match'] : null,
            array_key_exists('require_punch_photo', $validated) ? (bool) $validated['require_punch_photo'] : null,
        );

        return $this->success($settings, 'Face verification settings updated successfully.');
    }

    public function updateRegularization(UpdateAttendanceRegularizationSettingsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $settings = $this->attendanceSettingsService->updateRegularizationSettingsForCompany(
            (int) $request->user()->company_id,
            (bool) $validated['enabled'],
            array_key_exists('previous_month_cutoff_day', $validated)
                ? ($validated['previous_month_cutoff_day'] !== null ? (int) $validated['previous_month_cutoff_day'] : null)
                : null,
            array_key_exists('max_requests_per_month', $validated)
                ? ($validated['max_requests_per_month'] !== null ? (int) $validated['max_requests_per_month'] : null)
                : null,
            (bool) $validated['block_current_day_until_complete'],
        );

        return $this->success($settings, 'Regularization policy updated successfully.');
    }

    public function syncFaceReference(StoreAttendanceFaceReferenceRequest $request): JsonResponse
    {
        $payload = $this->attendanceSettingsService->syncFaceReference(
            $request->user(),
            $request->input('descriptor'),
        );

        return $this->success($payload, 'Face reference synced successfully.');
    }

    public function currentIp(Request $request): JsonResponse
    {
        $ip = $this->attendanceNetworkService->resolveClientIpFromRequest($request);

        return $this->success([
            'ip_address' => $ip,
        ]);
    }
}
