<?php

namespace App\Http\Controllers;

class EmployeeController extends Controller
{
    public function index()
    {
        return view('employees.index');
    }

    public function create()
    {
        return view('employees.create');
    }

    public function bulkImport()
    {
        return view('employees.bulk-import');
    }

    public function show(int $employee)
    {
        return view('employees.show', ['employeeId' => $employee]);
    }

    public function edit(int $employee)
    {
        return view('employees.edit', ['employeeId' => $employee]);
    }

    public function profileEdit(int $employee)
    {
        return view('employees.profile-edit', ['employeeId' => $employee]);
    }
}
