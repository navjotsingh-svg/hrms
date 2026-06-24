@extends('layouts.app')

@section('title', 'Leave Balances - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Leave Balances</h1>
            <p class="page-subtitle mb-0">View available leave for all employees at a glance. Open an employee to adjust balances or grant comp off.</p>
        </div>
        <a href="{{ route('web.leave.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="manageBalancesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="balanceYear" class="form-label">Year</label>
                    <select class="form-select" id="balanceYear"></select>
                </div>
                <div class="col-md-3">
                    <label for="balanceDepartment" class="form-label">Department</label>
                    <select class="form-select" id="balanceDepartment">
                        <option value="">All Departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="balanceStatus" class="form-label">Status</label>
                    <select class="form-select" id="balanceStatus">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="balanceSearch" class="form-label">Search</label>
                    <input type="search" class="form-control" id="balanceSearch" placeholder="Name or employee code">
                </div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="content-card-title mb-0" id="balancesOverviewTitle">Leave balance overview</h2>
            <div class="text-muted small" id="balancesPaginationInfo">—</div>
        </div>
        <div class="companies-table-wrap leave-balance-matrix-wrap">
            <table class="companies-table leave-balance-matrix mb-0">
                <thead id="leaveBalanceMatrixHead">
                    <tr>
                        <th colspan="4" class="text-center text-muted py-4">Loading...</th>
                    </tr>
                </thead>
                <tbody id="leaveBalanceMatrixBody"></tbody>
            </table>
        </div>
        <div class="content-card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="text-muted small" id="balancesPaginationSummary"></div>
            <ul class="pagination pagination-sm mb-0" id="balancesPaginationList"></ul>
        </div>
    </div>

    <div class="modal fade" id="employeeBalanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="employeeBalanceModalTitle">Employee balances</h5>
                        <div class="small text-muted" id="employeeBalanceModalSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="content-card mb-3 d-none" id="grantCompOffCard">
                        <div class="content-card-body">
                            <h6 class="mb-2">Grant Comp Off</h6>
                            <p class="small text-muted mb-3">Credit comp off days when an employee works on a holiday or weekly off.</p>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="grantCompOffDays" class="form-label">Days to grant</label>
                                    <input type="number" class="form-control" id="grantCompOffDays" min="0.5" step="0.5" value="1">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-success" id="grantCompOffBtn">Grant Comp Off</button>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted" id="compOffSummary">Comp off balance: —</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="companies-table table mb-0">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Annual Quota</th>
                                    <th>Allocated</th>
                                    <th>Credited</th>
                                    <th>Used</th>
                                    <th>Pending</th>
                                    <th>Available</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="manageBalancesTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/leaves-manage-balances.js'])
@endsection
