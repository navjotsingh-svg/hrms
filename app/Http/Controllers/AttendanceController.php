<?php

namespace App\Http\Controllers;

class AttendanceController extends Controller
{
    public function index()
    {
        return view('attendance.index');
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
