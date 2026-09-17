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

    public function create(): View
    {
        abort_unless(auth()->user()->canManageDocuments(), 403);

        return view('company-policies.form', [
            'mode' => 'create',
            'policyId' => null,
        ]);
    }

    public function edit(int $policy): View
    {
        abort_unless(auth()->user()->canManageDocuments(), 403);

        return view('company-policies.form', [
            'mode' => 'edit',
            'policyId' => $policy,
        ]);
    }

    public function show(int $policy): View
    {
        return view('company-policies.show', [
            'policyId' => $policy,
            'canManage' => auth()->user()->canManageDocuments(),
        ]);
    }
}
