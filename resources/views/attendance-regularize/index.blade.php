@extends('layouts.app')

@php
    $user = Auth::user();
    $isHrRegularizeView = $user->canApproveRegularization();
    $canSubmitRegularization = $user->canRegularizeAttendance() && $user->employee;
@endphp

@section('title', 'Attendance Regularization - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Attendance Regularization</h1>
            <p class="page-subtitle mb-0">
                @if ($isHrRegularizeView)
                    Manage your requests, review pending approvals, and browse history.
                @else
                    View your regularization requests or review pending approvals.
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('web.attendance.index') }}" class="btn btn-outline-secondary">View Attendance</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="regularizeAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    <div class="content-card mb-4">
        <div class="content-card-body companies-filter-bar">
            <div class="row g-3 align-items-end">
                @if ($user->canViewAllAttendance())
                <div class="col-md-4">
                    @include('partials.employee-search-select', [
                        'inputId' => 'regularizeEmployeeInput',
                        'hiddenId' => 'regularizeEmployeeId',
                        'label' => 'Employee',
                        'placeholder' => 'All employees — search by name or code',
                    ])
                </div>
                @endif
                <div class="col-md-3">
                    <label for="filterMonth" class="form-label">Month</label>
                    <input type="month" class="form-control" id="filterMonth">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" id="filterReset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="regularizeSummaryCards">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-card-primary h-100">
                <div class="stat-card-icon">📋</div>
                <div class="stat-card-body">
                    <p class="stat-card-label">Total Requests</p>
                    <h3 class="stat-card-value" id="regularizeSummaryTotal">—</h3>
                    <span class="stat-card-meta" id="regularizeSummaryMonthLabel">—</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-card-warning h-100">
                <div class="stat-card-icon">⏳</div>
                <div class="stat-card-body">
                    <p class="stat-card-label">Pending</p>
                    <h3 class="stat-card-value" id="regularizeSummaryPending">—</h3>
                    <span class="stat-card-meta">Awaiting approval</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-card-success h-100">
                <div class="stat-card-icon">✓</div>
                <div class="stat-card-body">
                    <p class="stat-card-label">Approved</p>
                    <h3 class="stat-card-value" id="regularizeSummaryApproved">—</h3>
                    <span class="stat-card-meta">Regularized</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card stat-card-danger h-100">
                <div class="stat-card-icon">✕</div>
                <div class="stat-card-body">
                    <p class="stat-card-label">Rejected</p>
                    <h3 class="stat-card-value" id="regularizeSummaryRejected">—</h3>
                    <span class="stat-card-meta">Not approved</span>
                </div>
            </div>
        </div>
    </div>

    @if ($isHrRegularizeView)
        <div class="regularize-section-tabs-wrap mb-3">
            <ul class="nav nav-tabs regularize-section-tabs" id="regularizeHrTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" type="button" data-regularize-tab="my-requests" role="tab">My Requests</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" data-regularize-tab="pending-approvals" role="tab">
                        Pending Approvals
                        <span class="badge rounded-pill text-bg-warning ms-1 d-none" id="regularizePendingBadge">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" type="button" data-regularize-tab="history" role="tab">History</button>
                </li>
            </ul>
        </div>

        <div id="regularizeTabMyRequests" class="regularize-tab-panel">
            @if ($canSubmitRegularization)
            <div class="content-card mb-4" id="regularizeSubmitCard">
                <div class="content-card-header border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h2 class="content-card-title mb-0">Request Regularization</h2>
                        <p class="small text-muted mb-0">Select one or more eligible days for the chosen month, then submit with a shared reason.</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllEligibleBtn">Select all</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="clearEligibleSelectionBtn">Clear</button>
                        <button type="button" class="btn btn-primary btn-sm" id="openRegularizeRequestBtn" disabled>Regularize selected (0)</button>
                    </div>
                </div>
                <div class="content-card-body">
                    <div id="eligibleDatesContainer" class="regularize-eligible-list">
                        <div class="text-muted py-3">Loading...</div>
                    </div>
                </div>
            </div>
            @endif

            <div class="content-card companies-list-card">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">My Requests</h2>
                    <p class="small text-muted mb-0">Requests you submitted for yourself or on behalf of employees.</p>
                </div>
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Original Times</th>
                                <th>Requested Times</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="myRequestsTableBody">
                            <tr><td colspan="8" class="text-center text-muted py-5">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="content-card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small text-muted" id="myRequestsPaginationInfo">—</div>
                    <ul class="pagination mb-0" id="myRequestsPaginationList"></ul>
                </div>
            </div>
        </div>

        <div id="regularizeTabPendingApprovals" class="regularize-tab-panel d-none">
            <div class="content-card">
                <div class="content-card-header border-bottom">
                    <div>
                        <h2 class="content-card-title mb-0">Pending Approvals</h2>
                        <p class="small text-muted mb-0">Review single-day or multi-day attendance correction requests.</p>
                    </div>
                </div>
                <div class="content-card-body">
                    <div id="pendingRegularizeContainer">
                        <div class="text-muted py-3">Loading pending requests...</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="regularizeTabHistory" class="regularize-tab-panel d-none">
            <div class="content-card companies-list-card">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">History</h2>
                </div>
                <div class="content-card-body companies-filter-bar border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="filterStatus" class="form-label">Status</label>
                            <select class="form-select" id="filterStatus">
                                <option value="">All completed</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Original Times</th>
                                <th>Requested Times</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="regularizeTableBody">
                            <tr><td colspan="8" class="text-center text-muted py-5">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="content-card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small text-muted" id="regularizePaginationInfo">—</div>
                    <ul class="pagination mb-0" id="regularizePaginationList"></ul>
                </div>
            </div>
        </div>
    @else
        @if ($canSubmitRegularization)
        <div class="content-card mb-4 d-none" id="myPendingRegularizeCard">
            <div class="content-card-header border-bottom">
                <div>
                    <h2 class="content-card-title mb-0">Your Pending Requests</h2>
                    <p class="small text-muted mb-0">Requests you submitted that are awaiting approval.</p>
                </div>
            </div>
            <div class="content-card-body">
                <div id="myPendingRegularizeContainer">
                    <div class="text-muted py-3">No pending requests.</div>
                </div>
            </div>
        </div>

        <div class="content-card mb-4" id="regularizeSubmitCard">
            <div class="content-card-header border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="content-card-title mb-0">Request Regularization</h2>
                    <p class="small text-muted mb-0">Select one or more eligible days for the chosen month, then submit with a shared reason.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllEligibleBtn">Select all</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="clearEligibleSelectionBtn">Clear</button>
                    <button type="button" class="btn btn-primary btn-sm" id="openRegularizeRequestBtn" disabled>Regularize selected (0)</button>
                </div>
            </div>
            <div class="content-card-body">
                <div id="eligibleDatesContainer" class="regularize-eligible-list">
                    <div class="text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
        @endif

        <div class="content-card companies-list-card">
            <div class="content-card-header border-bottom">
                <h2 class="content-card-title mb-0">Request History</h2>
            </div>
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
                </div>
            </div>
            <div class="table-responsive">
                <table class="companies-table table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Original Times</th>
                            <th>Requested Times</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="regularizeTableBody">
                        <tr><td colspan="8" class="text-center text-muted py-5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="content-card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="small text-muted" id="regularizePaginationInfo">—</div>
                <ul class="pagination mb-0" id="regularizePaginationList"></ul>
            </div>
        </div>
    @endif

    @include('attendance-regularize.partials.modals')
@endsection

@section('scripts')
    <script>
        window.regularizePageConfig = {
            isHrView: @json($isHrRegularizeView),
            defaultTab: 'my-requests',
        };
    </script>
    @vite(['resources/js/attendance-regularize.js'])
@endsection
