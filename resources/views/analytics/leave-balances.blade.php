@extends('layouts.app')

@section('title', 'Leave Balances Analytics - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('web.analytics.index') }}">Analytics</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('web.analytics.section', ['section' => 'leave']) }}">Leave</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Leave Balances</li>
                </ol>
            </nav>
            <h1 class="page-title mb-1">Leave Balances</h1>
            <p class="page-subtitle mb-0">Leave balances report by employee and policy for a selected date range.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary d-none" id="exportLeaveBalancesBtn">Export CSV</button>
        </div>
    </div>
@endsection

@section('content')
    @include('analytics.partials.tabs', [
        'sections' => $sections ?? [],
        'activeSection' => $activeSection ?? 'leave',
    ])

    <div id="leaveBalancesAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <ul class="nav nav-tabs mb-0" id="leaveAnalyticsTabs">
                <li class="nav-item">
                    <button class="nav-link active" type="button" data-analytics-tab="report">Leave Balances</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-analytics-tab="charts">Charts</button>
                </li>
            </ul>
        </div>

        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="filterFromDate" class="form-label">From Date *</label>
                    <input type="date" class="form-control" id="filterFromDate" required>
                </div>
                <div class="col-md-2">
                    <label for="filterToDate" class="form-label">To Date *</label>
                    <input type="date" class="form-control" id="filterToDate" required>
                </div>
                <div class="col-md-2">
                    <label for="filterEmployeeStatus" class="form-label">Employee Status</label>
                    <select class="form-select" id="filterEmployeeStatus">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterEmploymentType" class="form-label">Employment Type</label>
                    <select class="form-select" id="filterEmploymentType">
                        <option value="all" selected>All</option>
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterPolicyStatus" class="form-label">Policy Status</label>
                    <select class="form-select" id="filterPolicyStatus">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterAssignmentStatus" class="form-label">Assignment Status</label>
                    <select class="form-select" id="filterAssignmentStatus">
                        <option value="active" selected>Active</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterDepartmentId" class="form-label">Department</label>
                    <select class="form-select" id="filterDepartmentId">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterLeaveTypeId" class="form-label">Policy Name</label>
                    <select class="form-select" id="filterLeaveTypeId">
                        <option value="">All Policies</option>
                    </select>
                </div>
                <div class="col-md-4">
                    @include('partials.employee-search-select', [
                        'inputId' => 'filterEmployeeInput',
                        'hiddenId' => 'filterEmployeeId',
                        'label' => 'Employee',
                        'placeholder' => 'All employees',
                    ])
                </div>
                <div class="col-md-2">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Name or policy">
                </div>
                <div class="col-md-2">
                    <label for="itemsPerPage" class="form-label">Rows</label>
                    <select class="form-select" id="itemsPerPage">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" id="loadLeaveBalancesBtn">Load Report</button>
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div id="reportPanel">
            <div class="content-card-body border-bottom py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="text-muted small" id="leaveBalancesPaginationInfo">Select dates and load the report.</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 companies-table">
                    <thead id="leaveBalancesTableHead"></thead>
                    <tbody id="leaveBalancesTableBody">
                        <tr><td class="text-center text-muted py-5">Choose a date range and click Load Report.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="content-card-body border-top">
                <nav aria-label="Leave balances pagination">
                    <ul class="pagination justify-content-end mb-0" id="leaveBalancesPaginationList"></ul>
                </nav>
            </div>
        </div>

        <div id="chartsPanel" class="d-none">
            <div class="content-card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h2 class="h6">Balance Change by Department</h2>
                        <div id="chartDepartment" class="analytics-chart-wrap"></div>
                    </div>
                    <div class="col-lg-6">
                        <h2 class="h6">Leaves Taken by Policy</h2>
                        <div id="chartPolicy" class="analytics-chart-wrap"></div>
                    </div>
                    <div class="col-12">
                        <h2 class="h6">Employees by Balance Change Type</h2>
                        <div id="chartChangeType" class="analytics-chart-wrap"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="leaveBalanceDetailDrawer" aria-labelledby="leaveBalanceDetailDrawerLabel" style="width: min(480px, 100vw);">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title" id="leaveBalanceDetailDrawerLabel">Detailed Calculations</h5>
                <div class="small text-muted" id="leaveBalanceDetailSubtitle"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div id="leaveBalanceDetailSummary" class="mb-4"></div>
            <h6 class="mb-3">Timeline</h6>
            <div id="leaveBalanceDetailTimeline" class="analytics-timeline"></div>
        </div>
    </div>
@endsection

@section('scripts')
    @vite(['resources/js/leave-balances-analytics.js'])
@endsection
