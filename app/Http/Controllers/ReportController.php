<?php

namespace App\Http\Controllers;

class ReportController extends Controller
{
    public function index()
    {
        if (! auth()->user()->canViewReports()) {
            abort(403);
        }

        return view('reports.index', [
            'canManagePayroll' => auth()->user()->canManagePayroll(),
            'canViewLeaveAnalytics' => auth()->user()->canViewLeaveAnalytics(),
        ]);
    }
}
