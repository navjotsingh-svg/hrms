@extends('layouts.app')

@section('title', 'Asset Requests - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Asset Requests</h1>
            <p class="page-subtitle mb-0">Track asset requests and pending approvals.</p>
        </div>
        <div class="d-flex gap-2">
            @if (Auth::user()->canApplyAssets())
                <a href="{{ route('web.asset-requests.apply') }}" class="btn btn-primary">Request Asset</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="assetRequestsAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    @if (Auth::user()->canApproveAssets())
        <div class="content-card mb-4" id="assetPendingCard">
            <div class="content-card-header border-bottom d-flex align-items-center justify-content-between">
                <h2 class="content-card-title mb-0">Pending Approvals</h2>
                <span class="badge bg-warning text-dark d-none" id="assetPendingBadge">0</span>
            </div>
            <div class="content-card-body" id="assetPendingContainer">
                <div class="text-muted">Loading pending asset requests...</div>
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
                        <option value="partially_reviewed">Partially Reviewed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'assetRequestsPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Asset</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assetRequestsTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'assetRequestsPaginationInfo',
            'listId' => 'assetRequestsPaginationList',
            'perPageId' => 'assetRequestsPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Asset requests pagination',
        ])
    </div>
    @vite(['resources/js/assets-requests-index.js'])
@endsection
