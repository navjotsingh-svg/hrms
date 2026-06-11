<?php

namespace App\Http\Controllers;

class LeaveTypeController extends Controller
{
    public function index()
    {
        return view('leave-types.index');
    }

    public function create()
    {
        return view('leave-types.create');
    }

    public function edit(int $leaveType)
    {
        return view('leave-types.edit', ['leaveTypeId' => $leaveType]);
    }
}
