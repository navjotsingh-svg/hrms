<?php

namespace App\Http\Controllers;

use App\Services\AttendanceRegularizationService;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceRegularizationService $regularizationService) {}

    public function index()
    {
        $user = auth()->user();

        return view('attendance.index', [
            'canOpenRegularizationPanel' => $user->canRegularizeAttendance()
                && ($user->employee || $user->canManageRegularization())
                && $this->regularizationService->isEnabledForCompany((int) $user->company_id),
        ]);
    }

    public function today()
    {
        abort_unless(auth()->user()->canViewAllAttendance(), 403);

        return view('attendance.today');
    }

    public function overview()
    {
        $user = auth()->user();
        abort_unless($user->canViewAllAttendance() || $user->canViewTeamAttendance(), 403);

        return view('attendance.overview');
    }
}
