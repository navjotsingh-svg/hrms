@extends('layouts.app')

@section('title', 'Activity Logs - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Activity Logs</h1>
            <p class="page-subtitle mb-0">Company-wide audit trail with meaningful business events, attendance punch outcomes, and profile changes.</p>
        </div>
        <button type="button" class="btn btn-primary" id="loadActivityLogsBtn">Refresh</button>
    </div>
@endsection

@section('content')
    <div id="activityLogsAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card mb-3">
        <div class="content-card-body companies-filter-bar">
            <div class="row g-3 align-items-end">
                @if ($isSuperAdmin)
                    <div class="col-md-3">
                        <label for="activityLogCompanyId" class="form-label">Company</label>
                        <select class="form-select" id="activityLogCompanyId">
                            <option value="">Select company…</option>
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <label for="activityLogDate" class="form-label">Date</label>
                    <input type="date" class="form-control" id="activityLogDate">
                </div>
                <div class="col-md-2">
                    <label for="activityLogModule" class="form-label">Module</label>
                    <select class="form-select" id="activityLogModule">
                        <option value="">All modules</option>
                        <option value="attendance">Attendance</option>
                        <option value="auth">Auth</option>
                        <option value="leave">Leave</option>
                        <option value="payroll">Payroll</option>
                        <option value="expense">Expense</option>
                        <option value="employees">Employees</option>
                        <option value="performance">Performance</option>
                        <option value="hiring">Hiring</option>
                        <option value="requests">Requests</option>
                        <option value="reports">Reports</option>
                        <option value="profile">Profile</option>
                        <option value="system">System</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="activityLogStatus" class="form-label">Status</label>
                    <select class="form-select" id="activityLogStatus">
                        <option value="">All</option>
                        <option value="success">Success</option>
                        <option value="failure">Failure</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="activityLogSearch" class="form-label">Search</label>
                    <input type="search" class="form-control" id="activityLogSearch" placeholder="User, action, message, reason…">
                </div>
            </div>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 mb-0" id="activityLogsTitle">Activity log entries</h2>
                <div class="text-muted small" id="activityLogsSummary"></div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'activityLogsPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Message</th>
                        <th>Changes</th>
                        <th>Failure reason</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="activityLogsTableBody">
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Loading activity logs…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'activityLogsPaginationInfo',
            'listId' => 'activityLogsPaginationList',
            'perPageId' => 'activityLogsPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Activity logs pagination',
        ])
    </div>
@endsection

@push('scripts')
    @vite('resources/js/activity-logs-index.js')
@endpush
