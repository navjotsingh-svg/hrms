@extends('layouts.app')

@section('title', 'WFH Requests - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Work From Home</h1>
            <p class="page-subtitle mb-0">Track WFH requests and pending approvals.</p>
        </div>
        <div class="d-flex gap-2">
            @if (Auth::user()->canApplyWfh())
                <a href="{{ route('web.wfh.apply') }}" class="btn btn-primary">Apply WFH</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="wfhAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    @if (Auth::user()->canApproveWfh())
        <div class="content-card mb-4" id="wfhPendingCard">
            <div class="content-card-header border-bottom d-flex align-items-center justify-content-between">
                <h2 class="content-card-title mb-0">Pending Approvals</h2>
                <span class="badge bg-warning text-dark d-none" id="wfhPendingBadge">0</span>
            </div>
            <div class="content-card-body" id="wfhPendingContainer">
                <div class="text-muted">Loading pending WFH requests...</div>
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
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="wfhTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="wfhPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="wfhPaginationList"></ul>
            </div>
        </div>
    </div>
    @vite(['resources/js/wfh-index.js'])
@endsection
