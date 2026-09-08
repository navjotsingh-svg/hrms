<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

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
        $employeeModel = Employee::query()->findOrFail($employee);
        $user = Auth::user();

        abort_if(
            (int) $employeeModel->company_id !== (int) $user->company_id,
            404
        );

        return view('employees.show', [
            'employeeId' => $employee,
            'canInlineEditProfile' => $user->canEditEmployeeProfileWithoutApproval($employeeModel),
        ]);
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
