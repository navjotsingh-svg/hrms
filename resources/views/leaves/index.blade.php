@extends('layouts.app')

@section('title', 'Leave Management - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Leave Management</h1>
            <p class="page-subtitle mb-0">Apply, review, and track employee leave requests.</p>
        </div>
        <div class="d-flex gap-2">
            @if (Auth::user()->canManageLeaveBalances())
                <a href="{{ route('web.leave.manage-balances') }}" class="btn btn-outline-secondary">Manage Balances</a>
            @endif
            @if (Auth::user()->canApplyLeave())
                <a href="{{ route('web.leave.balances') }}" class="btn btn-outline-secondary">My Balances</a>
                <a href="{{ route('web.leave.apply') }}" class="btn btn-primary">Apply Leave</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="leavesAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    @if (Auth::user()->canApproveLeave())
    <div class="content-card mb-4">
        <div class="content-card-header border-bottom">
            <h2 class="content-card-title mb-0">Pending Approvals</h2>
        </div>
        <div class="content-card-body">
            @if (Auth::user()->isHrManager() && ! Auth::user()->isCompanyAdmin())
                <p class="small text-muted mb-3">Leave requests from HR employees are approved only by Company Admin.</p>
            @endif
            <div id="pendingLeavesContainer">
                <div class="text-muted py-3">Loading pending requests...</div>
            </div>
        </div>
    </div>
    @endif

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterYear" class="form-label">Year</label>
                    <select class="form-select" id="filterYear"></select>
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
                        <th>#</th>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="leavesTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="leavesPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="leavesPaginationList"></ul>
            </div>
        </div>
    </div>
    @vite(['resources/js/leaves-index.js'])
@endsection
