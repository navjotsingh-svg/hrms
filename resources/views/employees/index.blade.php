@extends('layouts.app')

@section('title', 'Employees - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Employees</h1>
            <p class="page-subtitle mb-0">{{ Auth::user()->canManageEmployees() ? 'Manage your company workforce.' : 'View employees in your reporting hierarchy.' }}</p>
        </div>
        @if (Auth::user()->canManageEmployees())
        <a href="{{ route('web.employees.create') }}" class="btn btn-primary" id="addEmployeeBtn">
            + Add Employee
        </a>
        @endif
    </div>
@endsection

@section('content')
    <div id="employeesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    @include('partials.employee-search-select', [
                        'inputId' => 'filterEmployeeInput',
                        'hiddenId' => 'filterEmployeeId',
                    ])
                </div>
                <div class="col-md-2">
                    <label for="filterDepartment" class="form-label">Department</label>
                    <select class="form-select" id="filterDepartment">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Portal</th>
                        <th>Status</th>
                        <th class="companies-th-actions d-none" id="employeesActionsHeader">Actions</th>
                    </tr>
                </thead>
                <tbody id="employeesTableBody">
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">Loading employees...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="content-card-body border-top companies-pagination-footer" id="employeesPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="employeesPaginationInfo">Loading pagination...</div>
                <nav aria-label="Employees pagination">
                    <ul class="pagination pagination-sm mb-0" id="employeesPaginationList"></ul>
                </nav>
            </div>
        </div>
    </div>
    @vite(['resources/js/employees-index.js'])
@endsection
