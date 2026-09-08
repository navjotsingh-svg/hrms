<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->canViewActivityLogs(), 403);

        return view('activity-logs.index', [
            'isSuperAdmin' => auth()->user()->isSuperAdmin(),
        ]);
    }
}
