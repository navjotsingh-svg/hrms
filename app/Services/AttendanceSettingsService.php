<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class AttendanceSettingsService
{
    public function __construct(
        private AttendanceNetworkService $attendanceNetworkService,
        private FaceVerificationService $faceVerificationService,
    ) {}

    public function networkSettingsForCompany(int $companyId): array
    {
        $allowedIps = $this->attendanceNetworkService->allowedIpsForCompany($companyId);

        return [
            'attendance_allowed_ips' => $allowedIps,
            'attendance_ip_restriction_enabled' => $allowedIps !== [],
            'face_match_threshold' => $this->faceVerificationService->thresholdPercent($companyId),
            'default_face_match_threshold' => $this->faceVerificationService->defaultThresholdPercent(),
            'company_face_match_threshold' => Company::query()
                ->whereKey($companyId)
                ->value('attendance_face_match_threshold'),
            'require_face_match' => $this->faceVerificationService->requiresFaceMatch($companyId),
            'default_require_face_match' => $this->faceVerificationService->defaultRequireFaceMatch(),
            'company_require_face_match' => Company::query()
                ->whereKey($companyId)
                ->value('attendance_require_face_match'),
        ];
    }

    /** @param  array<int, string>|null  $allowedIps */
    public function updateNetworkSettingsForCompany(int $companyId, ?array $allowedIps): array
    {
        Company::query()->findOrFail($companyId)->update([
            'attendance_allowed_ips' => $this->attendanceNetworkService->encodeAllowedIps($allowedIps ?? []),
        ]);

        return $this->networkSettingsForCompany($companyId);
    }

    public function updateFaceSettingsForCompany(int $companyId, ?int $faceMatchThreshold, ?bool $requireFaceMatch = null): array
    {
        $payload = [
            'attendance_face_match_threshold' => $faceMatchThreshold,
        ];

        if ($requireFaceMatch !== null) {
            $payload['attendance_require_face_match'] = $requireFaceMatch;
        }

        Company::query()->findOrFail($companyId)->update($payload);

        return $this->networkSettingsForCompany($companyId);
    }

    public function syncFaceReference(User $user, array $descriptor): array
    {
        $employee = $user->employee;

        if (! $employee) {
            abort(403, 'Employee profile is required.');
        }

        $this->faceVerificationService->syncProfileDescriptor($employee, $descriptor);

        return [
            'has_face_reference' => true,
            'face_match_threshold' => $this->faceVerificationService->thresholdPercent((int) $employee->company_id),
            'require_face_match' => $this->faceVerificationService->requiresFaceMatch((int) $employee->company_id),
        ];
    }
}
