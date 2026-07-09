@extends('layouts.app')

@section('title', 'Leave Management - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Leave Management</h1>
            <p class="page-subtitle mb-0">Apply and track employee leave requests.</p>
        </div>
        <div class="d-flex gap-2">
            @if (Auth::user()->canViewAllLeaveRequests())
                <a href="{{ route('web.leave.calendar') }}" class="btn btn-outline-secondary" title="Leave Calendar">
                    <span aria-hidden="true">&#128197;</span>
                    <span class="ms-1">Calendar</span>
                </a>
            @endif
            @if (Auth::user()->canManageLeaveBalances())
                <a href="{{ route('web.leave.manage-balances') }}" class="btn btn-outline-secondary">All Balances</a>
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
        @include('partials.list-pagination-header', ['perPageId' => 'leavesPerPage'])
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
        @include('partials.list-pagination-footer', [
            'infoId' => 'leavesPaginationInfo',
            'listId' => 'leavesPaginationList',
            'perPageId' => 'leavesPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Leaves pagination',
        ])
    </div>
    @vite(['resources/js/leaves-index.js'])
@endsection
