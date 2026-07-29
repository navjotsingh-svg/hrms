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
            'require_punch_photo' => $this->requiresPunchPhoto($companyId),
            'default_require_punch_photo' => $this->defaultRequirePunchPhoto(),
            'company_require_punch_photo' => Company::query()
                ->whereKey($companyId)
                ->value('attendance_require_punch_photo'),
            'regularization' => $this->regularizationSettingsForCompany($companyId),
        ];
    }

    public function regularizationSettingsForCompany(int $companyId): array
    {
        $company = Company::query()->whereKey($companyId)->first();
        $defaults = config('hrms.attendance.regularization', []);

        return [
            'enabled' => (bool) (
                $company?->attendance_regularization_enabled
                ?? ($defaults['enabled'] ?? true)
            ),
            'default_enabled' => (bool) ($defaults['enabled'] ?? true),
            'company_enabled' => $company?->attendance_regularization_enabled,
            'previous_month_cutoff_day' => (int) (
                $company?->attendance_regularization_previous_month_cutoff_day
                ?? ($defaults['previous_month_cutoff_day'] ?? 2)
            ),
            'default_previous_month_cutoff_day' => (int) ($defaults['previous_month_cutoff_day'] ?? 2),
            'company_previous_month_cutoff_day' => $company?->attendance_regularization_previous_month_cutoff_day,
            'max_requests_per_month' => $company?->attendance_regularization_max_requests_per_month
                ?? ($defaults['max_requests_per_month'] ?? null),
            'default_max_requests_per_month' => $defaults['max_requests_per_month'] ?? null,
            'company_max_requests_per_month' => $company?->attendance_regularization_max_requests_per_month,
            'block_current_day_until_complete' => (bool) (
                $company?->attendance_regularization_block_current_day_until_complete
                ?? ($defaults['block_current_day_until_complete'] ?? true)
            ),
            'default_block_current_day_until_complete' => (bool) (
                $defaults['block_current_day_until_complete'] ?? true
            ),
            'company_block_current_day_until_complete' => $company?->attendance_regularization_block_current_day_until_complete,
        ];
    }

    public function updateRegularizationSettingsForCompany(
        int $companyId,
        bool $enabled,
        ?int $previousMonthCutoffDay,
        ?int $maxRequestsPerMonth,
        bool $blockCurrentDayUntilComplete,
    ): array {
        Company::query()->findOrFail($companyId)->update([
            'attendance_regularization_enabled' => $enabled,
            'attendance_regularization_previous_month_cutoff_day' => $previousMonthCutoffDay,
            'attendance_regularization_max_requests_per_month' => $maxRequestsPerMonth,
            'attendance_regularization_block_current_day_until_complete' => $blockCurrentDayUntilComplete,
        ]);

        return $this->networkSettingsForCompany($companyId);
    }

    /** @param  array<int, string>|null  $allowedIps */
    public function updateNetworkSettingsForCompany(int $companyId, ?array $allowedIps): array
    {
        Company::query()->findOrFail($companyId)->update([
            'attendance_allowed_ips' => $this->attendanceNetworkService->encodeAllowedIps($allowedIps ?? []),
        ]);

        return $this->networkSettingsForCompany($companyId);
    }

    public function updateFaceSettingsForCompany(
        int $companyId,
        ?int $faceMatchThreshold,
        ?bool $requireFaceMatch = null,
        ?bool $requirePunchPhoto = null,
    ): array {
        $payload = [
            'attendance_face_match_threshold' => $faceMatchThreshold,
        ];

        if ($requireFaceMatch !== null) {
            $payload['attendance_require_face_match'] = $requireFaceMatch;
        }

        if ($requirePunchPhoto !== null) {
            $payload['attendance_require_punch_photo'] = $requirePunchPhoto;
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
            'require_punch_photo' => $this->requiresPunchPhoto((int) $employee->company_id),
        ];
    }

    public function defaultRequirePunchPhoto(): bool
    {
        return (bool) config('hrms.attendance.require_punch_photo', true);
    }

    public function requiresPunchPhoto(?int $companyId = null): bool
    {
        if ($companyId === null) {
            return $this->defaultRequirePunchPhoto();
        }

        $companySetting = Company::query()
            ->whereKey($companyId)
            ->value('attendance_require_punch_photo');

        if ($companySetting === null) {
            return $this->defaultRequirePunchPhoto();
        }

        return (bool) $companySetting;
    }
}
