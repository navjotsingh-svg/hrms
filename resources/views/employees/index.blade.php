@extends('layouts.app')

@section('title', 'Employees - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Employees</h1>
            <p class="page-subtitle mb-0">{{ Auth::user()->canManageEmployees() ? 'Manage your company workforce.' : 'View employees in your reporting hierarchy.' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (Auth::user()->canManageOffboarding() || Auth::user()->canManageEmployees())
            @if (Auth::user()->canManageOffboarding())
                <button type="button" class="btn btn-outline-primary" id="employeesStartOffboardingBtn">Start Offboarding</button>
            @endif
            @if (Auth::user()->canManageEmployees())
                <a href="{{ route('web.employees.bulk-import') }}" class="btn btn-outline-primary">Bulk Import</a>
                <a href="{{ route('web.employees.create') }}" class="btn btn-primary" id="addEmployeeBtn">
                    + Add Employee
                </a>
            @endif
            @endif
        </div>
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

        @include('partials.list-pagination-top', [
    'infoId' => 'employeesPaginationInfo',
    'listId' => 'employeesPaginationList',
    'perPageId' => 'employeesPerPage',
    'wrapId' => 'employeesPagination',
    'ariaLabel' => 'Employees pagination',
])

        <div id="employeesListContainer" data-can-manage-offboarding="{{ Auth::user()->canManageOffboarding() ? '1' : '0' }}">
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

    @if (Auth::user()->canManageOffboarding())
        <div class="modal fade" id="employeesStartOffboardingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="employeesStartOffboardingForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Start Offboarding</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Initiate offboarding for termination, retirement, or other exits without waiting for a resignation request.</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    @include('partials.employee-search-select', [
                                        'inputId' => 'employeesOffboardEmployeeSearch',
                                        'hiddenId' => 'employeesOffboardEmployeeId',
                                        'label' => 'Employee *',
                                        'placeholder' => 'Search active employee…',
                                        'required' => true,
                                    ])
                                </div>
                                <div class="col-md-6">
                                    <label for="employeesOffboardExitType" class="form-label">Exit Type *</label>
                                    <select class="form-select" id="employeesOffboardExitType" required>
                                        @foreach (config('offboarding.exit_types', []) as $value => $label)
                                            <option value="{{ $value }}" @selected($value === 'termination')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="employeesOffboardLastWorkingDate" class="form-label">Last Working Date *</label>
                                    <input type="date" class="form-control" id="employeesOffboardLastWorkingDate" required>
                                </div>
                                <div class="col-12">
                                    <label for="employeesOffboardNotes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="employeesOffboardNotes" rows="2" maxlength="2000" placeholder="Optional internal notes"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Start Offboarding</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/employees-index.js'])
@endpush
