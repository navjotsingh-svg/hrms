<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('dashboard');
    }

    public function dashboard(): View
    {
        return view('home.dashboard');
    }

    public function moments(): View
    {
        return view('home.moments');
    }
}
