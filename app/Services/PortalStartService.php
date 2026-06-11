<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use Carbon\Carbon;

class PortalStartService
{
    public function getForCompany(int $companyId): array
    {
        $company = Company::query()->findOrFail($companyId);
        $startDate = $company->attendance_portal_start_date;

        return [
            'attendance_portal_start_date' => $startDate?->toDateString(),
            'attendance_portal_start_date_label' => $startDate?->format('d M Y'),
            'is_configured' => $startDate !== null,
        ];
    }

    public function updateForCompany(int $companyId, ?string $startDate): array
    {
        $company = Company::query()->findOrFail($companyId);

        $company->update([
            'attendance_portal_start_date' => $startDate,
        ]);

        return $this->getForCompany($companyId);
    }

    public function portalStartDate(int $companyId): ?string
    {
        $date = Company::query()
            ->whereKey($companyId)
            ->value('attendance_portal_start_date');

        if (! $date) {
            return null;
        }

        return Carbon::parse($date)->toDateString();
    }

    public function hasPortalAccess(Employee $employee): bool
    {
        return $employee->portal_access_date !== null || $employee->user_id !== null;
    }

    public function effectivePortalAccessDate(Employee $employee): ?string
    {
        if ($employee->portal_access_date) {
            return $employee->portal_access_date->toDateString();
        }

        if (! $employee->user_id) {
            return null;
        }

        $employee->loadMissing('user');

        if ($employee->user?->created_at) {
            return $employee->user->created_at->toDateString();
        }

        return $employee->created_at?->toDateString();
    }

    /**
     * Attendance is tracked from the company portal start date or the
     * employee's joining date, whichever is later. The date the employee was
     * actually given portal access does NOT delay tracking: days between the
     * tracking start and the access grant show as absent and can be
     * regularized.
     */
    public function attendanceTrackingStartDate(Employee $employee): ?string
    {
        if (! $this->hasPortalAccess($employee)) {
            return null;
        }

        $candidates = [];

        $companyStart = $this->portalStartDate($employee->company_id);

        if ($companyStart) {
            $candidates[] = $companyStart;
        }

        if ($employee->joining_date) {
            $candidates[] = $employee->joining_date->toDateString();
        }

        if ($candidates === []) {
            return $this->effectivePortalAccessDate($employee);
        }

        return max($candidates);
    }

    public function isBeforeAttendanceTracking(Employee $employee, string $date): bool
    {
        if ($this->isBeforePortalStart($employee->company_id, $date)) {
            return true;
        }

        if (! $this->hasPortalAccess($employee)) {
            return true;
        }

        $trackingStart = $this->attendanceTrackingStartDate($employee);

        if (! $trackingStart) {
            return false;
        }

        return $date < $trackingStart;
    }

    public function beforeTrackingReason(Employee $employee, string $date): string
    {
        $portalStart = $this->portalStartDate($employee->company_id);

        if ($portalStart && $date < $portalStart) {
            return "Before company portal start ({$portalStart}).";
        }

        if (! $this->hasPortalAccess($employee)) {
            return 'Portal access has not been granted yet.';
        }

        $trackingStart = $this->attendanceTrackingStartDate($employee);

        if ($trackingStart && $date < $trackingStart) {
            $joining = $employee->joining_date?->toDateString();

            if ($joining && $date < $joining) {
                return "Before joining date ({$joining}).";
            }

            return "Attendance tracking starts from {$trackingStart} for this employee.";
        }

        return 'Attendance tracking has not started for this day.';
    }

    public function isBeforePortalStart(int $companyId, string $date): bool
    {
        $portalStart = $this->portalStartDate($companyId);

        if (! $portalStart) {
            return false;
        }

        return $date < $portalStart;
    }
}
