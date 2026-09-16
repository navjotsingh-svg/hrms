<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CompanyPolicyController extends Controller
{
    public function index(): View
    {
        return view('company-policies.index', [
            'canManage' => auth()->user()->canManageDocuments(),
        ]);
    }
}
