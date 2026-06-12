@extends('layouts.app')

@section('title', 'Payroll - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Payroll</h1>
            <p class="page-subtitle mb-0">Generate monthly payroll and view employee payslips.</p>
        </div>
    </div>
@endsection

@section('content')
    <div id="payrollAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <h2 class="h6 mb-3">Generate Payroll</h2>
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
                <div class="col-md-4 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" id="payrollViewBtn" disabled>View Payslip</button>
                    <button type="button" class="btn btn-outline-secondary" id="payrollDownloadBtn" disabled>Download</button>
                    <button type="button" class="btn btn-outline-success" id="payrollExportBtn" disabled>Export Excel</button>
                    <button type="button" class="btn btn-outline-danger" id="payrollDeletePeriodBtn" disabled>Delete Period</button>
                </div>
            </div>

            <div id="payrollSummaryWrap" class="d-none mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <h2 class="h6 mb-0">Payout Summary <span class="text-muted fw-normal" id="payrollSummaryPeriodLabel"></span></h2>
                    <span class="text-muted small" id="payrollSummaryTotals"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th class="text-end">Payable Days</th>
                                <th class="text-end">LOP Days</th>
                                <th class="text-end">Gross Salary</th>
                                <th class="text-end">Net Salary</th>
                                <th class="text-center">Payslip Preview</th>
                            </tr>
                        </thead>
                        <tbody id="payrollSummaryBody"></tbody>
                    </table>
                </div>
            </div>

            <div id="payrollViewerEmpty" class="text-center text-muted py-5">
                Select a payroll period and employee, then click View Payslip.
            </div>
            <div id="payrollViewerWrap" class="d-none">
                <iframe id="payrollViewerFrame" title="Payslip preview" style="width: 100%; min-height: 720px; border: 1px solid #dee2e6; border-radius: 8px;"></iframe>
            </div>
        </div>
    </div>

    <script>window.PAYROLL_MODE = 'manage';</script>
    @vite(['resources/js/payroll.js'])
@endsection
