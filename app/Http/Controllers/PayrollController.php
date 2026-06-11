<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class PayrollController extends Controller
{
    public function index()
    {
        if (! Auth::user()->canManagePayroll()) {
            abort(403);
        }

        return view('payroll.index');
    }

    public function myPayslips()
    {
        if (! Auth::user()->canViewPayroll()) {
            abort(403);
        }

        return view('payroll.my-payslips', [
            'canManagePayroll' => Auth::user()->canManagePayroll(),
        ]);
    }
}
