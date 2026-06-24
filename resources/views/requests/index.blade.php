@extends('layouts.app')

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

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="requests-toolbar">
                <div class="requests-icon-tabs" role="tablist" aria-label="Request views">
                    @if (Auth::user()->canApproveLeave() || Auth::user()->canApproveRegularization() || Auth::user()->canReviewEmployeeDocuments() || Auth::user()->canApproveExpenses())
                    <button
                        type="button"
                        class="requests-icon-tab active"
                        id="requestsTabApproval"
                        data-requests-tab="approval"
                        role="tab"
                        aria-selected="true"
                        title="For approval"
                        aria-label="For approval"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg>
                        <span class="requests-icon-tab-badge d-none" id="requestsPendingBadge">0</span>
                    </button>
                    <button
                        type="button"
                        class="requests-icon-tab"
                        id="requestsTabTeam"
                        data-requests-tab="team"
                        role="tab"
                        aria-selected="false"
                        title="Employee requests"
                        aria-label="Employee requests"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/></svg>
                    </button>
                    @endif
                    <button
                        type="button"
                        class="requests-icon-tab {{ Auth::user()->canApproveLeave() || Auth::user()->canApproveRegularization() || Auth::user()->canReviewEmployeeDocuments() || Auth::user()->canApproveExpenses() ? '' : 'active' }}"
                        id="requestsTabMine"
                        data-requests-tab="mine"
                        role="tab"
                        aria-selected="{{ Auth::user()->canApproveLeave() || Auth::user()->canApproveRegularization() || Auth::user()->canReviewEmployeeDocuments() || Auth::user()->canApproveExpenses() ? 'false' : 'true' }}"
                        title="My requests"
                        aria-label="My requests"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/></svg>
                    </button>
                </div>

                <div class="requests-filters ms-auto">
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
