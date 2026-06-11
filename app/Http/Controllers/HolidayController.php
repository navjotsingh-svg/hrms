<?php

namespace App\Http\Controllers;

class HolidayController extends Controller
{
    public function index()
    {
        return view('holidays.index');
    }

    public function create()
    {
        return view('holidays.create');
    }

    public function edit(int $holiday)
    {
        return view('holidays.edit', ['holidayId' => $holiday]);
    }
}
