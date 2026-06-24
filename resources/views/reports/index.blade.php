@extends('layouts.app')

@section('title', 'Reports & Export - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Reports &amp; Export</h1>
            <p class="page-subtitle mb-0">Real-time HR reports with live data preview and Excel export.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="loadReportBtn">Load Report</button>
            <button type="button" class="btn btn-primary" id="exportReportBtn" disabled>Export Excel</button>
        </div>
    </div>
@endsection

@section('content')
    <div id="reportsAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card mb-3">
        <div class="content-card-body companies-filter-bar">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="reportTypeSelect" class="form-label">Report *</label>
                    <select class="form-select" id="reportTypeSelect">
                        <option value="">Select a report…</option>
                    </select>
                    <div class="form-text" id="reportDescription"></div>
                </div>
                <div class="col-md-2 filter-field d-none" data-filter="from_date">
                    <label for="filterFromDate" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="filterFromDate">
                </div>
                <div class="col-md-2 filter-field d-none" data-filter="to_date">
                    <label for="filterToDate" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="filterToDate">
                </div>
                <div class="col-md-2 filter-field d-none" data-filter="status">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="department_id">
                    <label for="filterDepartmentId" class="form-label">Department</label>
                    <select class="form-select" id="filterDepartmentId">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="employee_id">
                    <label for="filterEmployeeSearch" class="form-label">Employee</label>
                    <input type="text" class="form-control" id="filterEmployeeSearch" placeholder="Search employee" autocomplete="off">
                    <input type="hidden" id="filterEmployeeId">
                </div>
                <div class="col-md-2 filter-field d-none" data-filter="employment_type">
                    <label for="filterEmploymentType" class="form-label">Employment Type</label>
                    <select class="form-select" id="filterEmploymentType">
                        <option value="">All</option>
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="leave_type_id">
                    <label for="filterLeaveTypeId" class="form-label">Leave Type</label>
                    <select class="form-select" id="filterLeaveTypeId">
                        <option value="">All Leave Types</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="payroll_period_id">
                    <label for="filterPayrollPeriodId" class="form-label">Payroll Period *</label>
                    <select class="form-select" id="filterPayrollPeriodId">
                        <option value="">Select period…</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="cycle_id">
                    <label for="filterCycleId" class="form-label">Review Cycle</label>
                    <select class="form-select" id="filterCycleId">
                        <option value="">All Cycles</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="project_id">
                    <label for="filterProjectId" class="form-label">Project</label>
                    <select class="form-select" id="filterProjectId">
                        <option value="">All Projects</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 mb-0" id="reportPreviewTitle">Report Preview</h2>
                <p class="small text-muted mb-0" id="reportGeneratedAt">Select a report and click Load Report.</p>
            </div>
            <div class="text-muted small" id="reportsPaginationInfo"></div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead id="reportsTableHead">
                    <tr><th class="text-muted fw-normal">No data loaded</th></tr>
                </thead>
                <tbody id="reportsTableBody">
                    <tr><td class="text-center text-muted py-4">Choose a report type to begin.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <ul class="pagination pagination-sm mb-0 justify-content-end" id="reportsPaginationList"></ul>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/reports-index.js')
@endpush
