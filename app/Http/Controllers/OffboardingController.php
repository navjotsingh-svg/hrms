<?php

namespace App\Http\Controllers;

class OffboardingController extends Controller
{
    public function apply()
    {
        return view('offboarding.apply');
    }

    public function index()
    {
        return view('offboarding.index');
    }

    public function show(int $exitCase)
    {
        return view('offboarding.show', ['exitCaseId' => $exitCase]);
    }
}
