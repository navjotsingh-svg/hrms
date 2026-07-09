@extends('layouts.app')

@section('title', 'Employees - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Employees</h1>
            <p class="page-subtitle mb-0">{{ Auth::user()->canManageEmployees() ? 'Manage your company workforce.' : 'View employees in your reporting hierarchy.' }}</p>
        </div>
        @if (Auth::user()->canManageEmployees())
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('web.employees.bulk-import') }}" class="btn btn-outline-primary">Bulk Import</a>
            <a href="{{ route('web.employees.create') }}" class="btn btn-primary" id="addEmployeeBtn">
                + Add Employee
            </a>
        </div>
        @endif
    </div>
@endsection

@section('content')
    <div id="employeesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="employees-filter-toolbar">
                <div class="employees-filter-fields row g-3 align-items-end">
                    <div class="col-lg-4 col-md-5">
                        @include('partials.employee-search-select', [
                            'inputId' => 'filterEmployeeInput',
                            'hiddenId' => 'filterEmployeeId',
                        ])
                    </div>
                    <div class="col-lg-3 col-md-3">
                        <label for="filterDepartment" class="form-label">Department</label>
                        <select class="form-select" id="filterDepartment">
                            <option value="">All departments</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-2">
                        <label for="filterStatus" class="form-label">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="employees-filter-actions">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                    <div class="employees-layout-toggle" role="group" aria-label="Employee list layout">
                        <button type="button" class="employees-layout-btn employees-layout-btn--active" data-layout="table" title="Table view" aria-pressed="true" aria-label="Table view">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                            </svg>
                        </button>
                        <button type="button" class="employees-layout-btn" data-layout="cards" title="Card view" aria-pressed="false" aria-label="Card view">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.list-pagination-header', ['perPageId' => 'employeesPerPage'])

        <div id="employeesListContainer">
            <div id="employeesTableView" class="employees-table-view">
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th class="companies-th-serial">#</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Portal</th>
                                <th>Status</th>
                                <th class="companies-th-actions d-none" id="employeesActionsHeader">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeesTableBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">Loading employees...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="employeesCardView" class="employees-card-view d-none">
                <div class="employees-card-grid" id="employeesCardGrid">
                    <div class="employees-card-loading text-center text-muted py-5">Loading employees...</div>
                </div>
            </div>
        </div>

        @include('partials.list-pagination-footer', [
            'infoId' => 'employeesPaginationInfo',
            'listId' => 'employeesPaginationList',
            'perPageId' => 'employeesPerPage',
            'wrapId' => 'employeesPagination',
            'ariaLabel' => 'Employees pagination',
            'infoText' => 'Loading pagination...',
        ])
    </div>
    @vite(['resources/js/employees-index.js'])
@endsection
