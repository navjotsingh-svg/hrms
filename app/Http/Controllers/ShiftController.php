<?php

namespace App\Http\Controllers;

class ShiftController extends Controller
{
    public function index()
    {
        return view('shifts.index');
    }

    public function create()
    {
        return view('shifts.create');
    }

    public function edit(int $shift)
    {
        return view('shifts.edit', ['shiftId' => $shift]);
    }
}
