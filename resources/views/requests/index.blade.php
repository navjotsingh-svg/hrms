@extends('layouts.app')

@php
    $canReviewRequests = Auth::user()->canApproveLeave()
        || Auth::user()->canApproveRegularization()
        || Auth::user()->canReviewEmployeeDocuments()
        || Auth::user()->canApproveExpenses();
@endphp

@section('title', 'Requests - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Requests</h1>
            <p class="page-subtitle mb-0">Review pending approvals, track employee request outcomes, and view your own submissions.</p>
        </div>
    </div>
@endsection

@section('content')
    <div id="requestsAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    <div class="row g-3 mb-3" id="requestsSummaryCards">
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="stat-card-link w-100 border-0 bg-transparent p-0 text-start" data-requests-status="">
                <div class="stat-card stat-card-primary stat-card-clickable h-100">
                    <div class="stat-card-icon">📋</div>
                    <div class="stat-card-body">
                        <p class="stat-card-label">Total</p>
                        <h3 class="stat-card-value" id="requestsCountTotal">0</h3>
                        <span class="stat-card-meta" id="requestsCountMeta">All requests</span>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="stat-card-link w-100 border-0 bg-transparent p-0 text-start" data-requests-status="pending">
                <div class="stat-card stat-card-warning stat-card-clickable h-100">
                    <div class="stat-card-icon">⏳</div>
                    <div class="stat-card-body">
                        <p class="stat-card-label">Pending</p>
                        <h3 class="stat-card-value" id="requestsCountPending">0</h3>
                        <span class="stat-card-meta">Awaiting action</span>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="stat-card-link w-100 border-0 bg-transparent p-0 text-start" data-requests-status="approved">
                <div class="stat-card stat-card-success stat-card-clickable h-100">
                    <div class="stat-card-icon">✓</div>
                    <div class="stat-card-body">
                        <p class="stat-card-label">Approved</p>
                        <h3 class="stat-card-value" id="requestsCountApproved">0</h3>
                        <span class="stat-card-meta">Completed</span>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="stat-card-link w-100 border-0 bg-transparent p-0 text-start" data-requests-status="rejected">
                <div class="stat-card stat-card-danger stat-card-clickable h-100">
                    <div class="stat-card-icon">✕</div>
                    <div class="stat-card-body">
                        <p class="stat-card-label">Rejected</p>
                        <h3 class="stat-card-value" id="requestsCountRejected">0</h3>
                        <span class="stat-card-meta">Declined</span>
                    </div>
                </div>
            </button>
        </div>
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="stat-card-link w-100 border-0 bg-transparent p-0 text-start" data-requests-status="cancelled">
                <div class="stat-card stat-card-info stat-card-clickable h-100">
                    <div class="stat-card-icon">↩</div>
                    <div class="stat-card-body">
                        <p class="stat-card-label">Cancelled</p>
                        <h3 class="stat-card-value" id="requestsCountCancelled">0</h3>
                        <span class="stat-card-meta">Withdrawn</span>
                    </div>
                </div>
            </button>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="requests-toolbar">
                <div class="requests-view-tabs" role="tablist" aria-label="Request views">
                    @if ($canReviewRequests)
                    <button
                        type="button"
                        class="requests-view-tab active"
                        id="requestsTabApproval"
                        data-requests-tab="approval"
                        role="tab"
                        aria-selected="true"
                        aria-label="For approval"
                    >
                        <span class="requests-view-tab-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg>
                        </span>
                        <span class="requests-view-tab-label">For approval</span>
                        <span class="requests-view-tab-badge d-none" id="requestsPendingBadge">0</span>
                    </button>
                    <button
                        type="button"
                        class="requests-view-tab"
                        id="requestsTabTeam"
                        data-requests-tab="team"
                        role="tab"
                        aria-selected="false"
                        aria-label="Employee requests"
                    >
                        <span class="requests-view-tab-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/></svg>
                        </span>
                        <span class="requests-view-tab-label">Employee requests</span>
                    </button>
                    @endif
                    <button
                        type="button"
                        class="requests-view-tab {{ $canReviewRequests ? '' : 'active' }}"
                        id="requestsTabMine"
                        data-requests-tab="mine"
                        role="tab"
                        aria-selected="{{ $canReviewRequests ? 'false' : 'true' }}"
                        aria-label="My requests"
                    >
                        <span class="requests-view-tab-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/></svg>
                        </span>
                        <span class="requests-view-tab-label">My requests</span>
                    </button>
                </div>

                <div class="requests-filters ms-auto">
                    @if ($canReviewRequests)
                    <div class="requests-employee-filter d-none" id="requestsEmployeeFilterWrap">
                        @include('partials.employee-search-select', [
                            'inputId' => 'requestsEmployeeInput',
                            'hiddenId' => 'requestsEmployeeId',
                            'label' => 'Employee',
                            'placeholder' => 'All employees — search by name or code',
                            'wrapClass' => 'requests-employee-filter-select',
                        ])
                    </div>
                    @endif
                    <label class="requests-filter-select-wrap" title="Filter from date" aria-label="Filter from date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/></svg>
                        <input type="date" class="form-control form-control-sm" id="requestsDateFrom" title="From date">
                    </label>
                    <label class="requests-filter-select-wrap" title="Filter to date" aria-label="Filter to date">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/></svg>
                        <input type="date" class="form-control form-control-sm" id="requestsDateTo" title="To date">
                    </label>
                    <label class="requests-filter-select-wrap" title="Filter by status" aria-label="Filter by status">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5z"/></svg>
                        <select class="form-select form-select-sm" id="requestsStatusFilter">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </label>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="requestsFilterReset">Reset</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Request</th>
                        <th>Details</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @include('expenses.partials.request-detail-modal')

    @vite(['resources/js/requests-index.js'])
@endsection
