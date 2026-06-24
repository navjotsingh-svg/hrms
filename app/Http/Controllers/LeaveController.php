<?php

namespace App\Http\Controllers;

class LeaveController extends Controller
{
    public function index()
    {
        return view('leaves.index');
    }

    public function create()
    {
        return view('leaves.create');
    }

    public function show(int $leave)
    {
        return view('leaves.show', ['leaveId' => $leave]);
    }

    public function balances()
    {
        return view('leaves.balances');
    }

    public function manageBalances()
    {
        return view('leaves.manage-balances');
    }

    public function calendar()
    {
        abort_unless(auth()->user()?->canViewAllLeaveRequests(), 403);

        return view('leaves.calendar');
    }
}
