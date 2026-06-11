@extends('layouts.app')

@section('title', 'My Payslips - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">My Payslips</h1>
            <p class="page-subtitle mb-0">View and download your monthly payslips.</p>
        </div>
        @if ($canManagePayroll)
            <a href="{{ route('web.payroll.index') }}" class="btn btn-outline-primary">Manage Payroll</a>
        @endif
    </div>
@endsection

@section('content')
    <div id="payrollAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <div class="row g-3 align-items-end mb-4">
                <div class="col-md-6">
                    <label for="payrollPeriodSelect" class="form-label">Select Payroll Period</label>
                    <select class="form-select" id="payrollPeriodSelect">
                        <option value="">Choose period...</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" id="payrollViewBtn" disabled>View Payslip</button>
                    <button type="button" class="btn btn-outline-secondary" id="payrollDownloadBtn" disabled>Download</button>
                </div>
            </div>

            <div id="payrollViewerEmpty" class="text-center text-muted py-5">
                Select a payroll period, then click View Payslip.
            </div>
            <div id="payrollViewerWrap" class="d-none">
                <iframe id="payrollViewerFrame" title="Payslip preview" style="width: 100%; min-height: 720px; border: 1px solid #dee2e6; border-radius: 8px;"></iframe>
            </div>
        </div>
    </div>

    <script>window.PAYROLL_MODE = 'employee';</script>
    @vite(['resources/js/payroll.js'])
@endsection
