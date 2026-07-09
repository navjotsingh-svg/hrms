@extends('layouts.app')

@section('title', 'Payroll - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Payroll</h1>
            <p class="page-subtitle mb-0">Generate monthly payroll and process final settlement for offboarded employees.</p>
        </div>
    </div>
@endsection

@section('content')
    <div id="payrollAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <h2 class="h6 mb-3">Generate Payroll</h2>
            <p class="small text-muted mb-3">Payroll is calculated from employee salary components and attendance for the selected month. Employees leaving in that month are excluded and paid separately under offboard payroll.</p>
            <form id="payrollGenerateForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="payrollYear" class="form-label">Year</label>
                    <select class="form-select" id="payrollYear" required></select>
                </div>
                <div class="col-md-3">
                    <label for="payrollMonth" class="form-label">Month</label>
                    <select class="form-select" id="payrollMonth" required></select>
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" id="payrollGenerateBtn">Generate Payroll</button>
                    <button type="button" class="btn btn-outline-warning" id="payrollRegenerateBtn">Regenerate Payroll</button>
                </div>
            </form>
        </div>
    </div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <h2 class="h6 mb-3">Pay Offboard Employee</h2>
            <p class="small text-muted mb-3">Generate final payroll for employees who exited during offboarding. Once paid, they will not appear here again. Employee records and history are retained; portal login is disabled after payment.</p>
            <form id="payrollOffboardForm" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="payrollOffboardEmployee" class="form-label">Offboarded employee</label>
                    <select class="form-select" id="payrollOffboardEmployee" required>
                        <option value="">Loading eligible employees...</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary" id="payrollOffboardGenerateBtn">Generate Offboard Payroll</button>
                    <button type="button" class="btn btn-outline-secondary" id="payrollOffboardRefreshBtn">Refresh List</button>
                </div>
            </form>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-body">
            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-4">
                    <label for="payrollPeriodSelect" class="form-label">Select Payroll Period</label>
                    <select class="form-select" id="payrollPeriodSelect">
                        <option value="">Choose period...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    @include('partials.employee-search-select', [
                        'inputId' => 'payrollEmployeeInput',
                        'hiddenId' => 'payrollEmployeeId',
                        'placeholder' => 'Select period first...',
                    ])
                </div>
                <div class="col-md-4 d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" class="btn btn-primary" id="payrollViewBtn" disabled>View Payslip</button>
                    <button type="button" class="btn btn-outline-secondary" id="payrollDownloadBtn" disabled>Download</button>
                    <button type="button" class="btn btn-outline-success" id="payrollExportBtn" disabled>Export Excel</button>
                    <button type="button" class="btn btn-success" id="payrollMarkPaidBtn" disabled>Mark as Paid</button>
                </div>
            </div>

            <div id="payrollPeriodStatus" class="payroll-period-status d-none mb-3"></div>

            <div id="payrollSummaryWrap" class="d-none mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h6 mb-0">Payout Summary <span class="text-muted fw-normal" id="payrollSummaryPeriodLabel"></span></h2>
                    <span class="text-muted small" id="payrollSummaryTotals"></span>
                </div>

                <div class="payroll-summary-toolbar mb-3">
                    <div class="payroll-summary-search-wrap">
                        <input
                            type="search"
                            class="form-control payroll-summary-search"
                            id="payrollSummarySearch"
                            placeholder="Search Employees"
                            autocomplete="off"
                        >
                        <span class="payroll-summary-search-icon" aria-hidden="true">&#128269;</span>
                    </div>
                </div>

        @include('partials.list-pagination-header', ['perPageId' => 'payrollSummaryPerPage'])
        <div class="table-responsive">
                    <table class="table table-hover align-middle payroll-summary-table">
                        <thead id="payrollSummaryHead"></thead>
                        <tbody id="payrollSummaryBody"></tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'payrollSummaryPaginationInfo',
                    'listId' => 'payrollSummaryPaginationList',
                    'perPageId' => 'payrollSummaryPerPage',
                    'wrapId' => 'payrollSummaryPaginationWrap',
                    'wrapClass' => 'content-card-body border-top companies-pagination-footer',
                    'ariaLabel' => 'Payroll summary pagination',
                ])
            </div>

            <div id="payrollViewerEmpty" class="text-center text-muted py-5">
                Select a payroll period and employee, then click View Payslip.
            </div>
            <div id="payrollViewerWrap" class="d-none">
                <iframe id="payrollViewerFrame" title="Payslip preview" style="width: 100%; min-height: 720px; border: 1px solid #dee2e6; border-radius: 8px;"></iframe>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end payroll-detail-drawer" tabindex="-1" id="payrollDetailDrawer" aria-labelledby="payrollDetailDrawerLabel">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title" id="payrollDetailDrawerLabel">Detailed Calculations</h5>
                <div class="small text-muted" id="payrollDetailSubtitle"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" id="payrollDetailBody"></div>
    </div>

    <script>window.PAYROLL_MODE = 'manage';</script>
    @vite(['resources/js/payroll.js'])
@endsection
