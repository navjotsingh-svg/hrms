@extends('layouts.app')

@section('title', $reportName . ' - Analytics - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('web.analytics.index') }}">Analytics</a></li>
                    @if ($sectionKey)
                        <li class="breadcrumb-item">
                            <a href="{{ route('web.analytics.section', ['section' => $sectionKey]) }}">{{ ucfirst($sectionKey) }}</a>
                        </li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">{{ $reportName }}</li>
                </ol>
            </nav>
            <h1 class="page-title mb-1">{{ $reportName }}</h1>
            <p class="page-subtitle mb-0">{{ $reportDescription }}</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="analyticsAiSummarizeBtn" disabled>AI summarize</button>
            <button type="button" class="btn btn-outline-secondary" id="exportAnalyticsReportBtn" disabled>
                {{ $exportType === 'excel' ? 'Export Excel' : 'Export CSV' }}
            </button>
        </div>
    </div>
@endsection

@section('content')
    @include('analytics.partials.tabs', [
        'sections' => $sections,
        'activeSection' => $sectionKey,
    ])

    <div id="analyticsReportAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>
    <div id="analyticsAiSummary" class="alert alert-info d-none" role="status"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <ul class="nav nav-tabs mb-0" id="analyticsReportTabs">
                <li class="nav-item">
                    <button class="nav-link active" type="button" data-analytics-tab="report">Report</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" type="button" data-analytics-tab="charts">Charts</button>
                </li>
            </ul>
        </div>

        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end" id="analyticsReportFilters">
                <div class="col-md-2 filter-field d-none" data-filter="from_date">
                    <label for="filterFromDate" class="form-label">From Date *</label>
                    <input type="date" class="form-control" id="filterFromDate">
                </div>
                <div class="col-md-2 filter-field d-none" data-filter="to_date">
                    <label for="filterToDate" class="form-label">To Date *</label>
                    <input type="date" class="form-control" id="filterToDate">
                </div>
                <div class="col-md-2 filter-field d-none" data-filter="status">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus"></select>
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
                <div class="col-md-2 filter-field d-none" data-filter="date_type">
                    <label for="filterDateType" class="form-label">Date Type *</label>
                    <select class="form-select" id="filterDateType">
                        <option value="expense_date" selected>Expense Date</option>
                        <option value="created_on">Created On Date</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="department_id">
                    <label for="filterDepartmentId" class="form-label">Department</label>
                    <select class="form-select" id="filterDepartmentId">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="employee_id">
                    @include('partials.employee-search-select', [
                        'inputId' => 'filterEmployeeInput',
                        'hiddenId' => 'filterEmployeeId',
                        'label' => 'Employee',
                        'placeholder' => 'All employees',
                    ])
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="cycle_id">
                    <label for="filterCycleId" class="form-label">Review Cycle *</label>
                    <select class="form-select" id="filterCycleId">
                        <option value="">Select review cycle…</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="job_id">
                    <label for="filterJobId" class="form-label">Job Title</label>
                    <select class="form-select" id="filterJobId">
                        <option value="">All Jobs</option>
                    </select>
                </div>
                <div class="col-md-3 filter-field d-none" data-filter="candidate_status">
                    <label for="filterCandidateStatus" class="form-label">Candidate Status</label>
                    <select class="form-select" id="filterCandidateStatus">
                        <option value="">All</option>
                    </select>
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
                    <button type="button" class="btn btn-primary" id="loadAnalyticsReportBtn">Load Report / Charts</button>
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div id="analyticsReportPanel">
        <div class="content-card-body border-bottom py-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="text-muted small" id="analyticsReportPaginationInfo">Configure filters and load the report.</span>
                <span class="text-muted small d-none" id="analyticsReportGeneratedAt"></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 companies-table">
                <thead id="analyticsReportTableHead"></thead>
                <tbody id="analyticsReportTableBody">
                    <tr><td class="text-center text-muted py-5">Set filters and click Load Report / Charts.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="content-card-body border-top">
            <nav aria-label="Analytics report pagination">
                <ul class="pagination justify-content-end mb-0" id="analyticsReportPaginationList"></ul>
            </nav>
        </div>
        </div>

        <div id="analyticsChartsPanel" class="d-none">
            <div class="content-card-body">
                <div id="analyticsChartsContainer" class="row g-4">
                    <div class="col-12 text-muted py-5 text-center">Load the report to view charts.</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        window.analyticsReportConfig = {
            reportKey: @json($reportKey),
            filters: @json($filters),
            exportType: @json($exportType),
        };
    </script>
    @vite(['resources/js/analytics-report.js'])
@endsection
