<?php

namespace App\Http\Controllers;

class DepartmentController extends Controller
{
    public function index()
    {
        return view('departments.index');
    }

    public function create()
    {
        return view('departments.create');
    }

    public function edit(int $department)
    {
        return view('departments.edit', ['departmentId' => $department]);
    }
}
