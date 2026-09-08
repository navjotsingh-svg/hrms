<?php

namespace App\Http\Controllers;

class EmployeeAssistantController extends Controller
{
    public function index()
    {
        abort_unless(config('hrms.assistant.enabled', true), 404);
        abort_unless(auth()->user()?->company_id, 403);

        return view('assistant.index');
    }
}
