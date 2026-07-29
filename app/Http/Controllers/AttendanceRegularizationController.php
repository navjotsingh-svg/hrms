<?php

namespace App\Http\Controllers;

use App\Services\AttendanceRegularizationService;

class AttendanceRegularizationController extends Controller
{
    public function __construct(private AttendanceRegularizationService $regularizationService) {}

    public function index()
    {
        $user = auth()->user();
        $params = ['regularize' => 1];

        if (! $user->canRegularizeAttendance()
            || (! $user->employee && ! $user->canManageRegularization())
            || ! $this->regularizationService->isEnabledForCompany((int) $user->company_id)) {
            return redirect()->route('web.attendance.index');
        }

        return redirect()->route('web.attendance.index', $params);
    }
}
