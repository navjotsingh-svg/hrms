@extends('layouts.app')

@section('title', 'Leave Types - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Leave Types</h1>
            <p class="page-subtitle mb-0">Configure leave categories and annual quotas.</p>
        </div>
        <a href="{{ route('web.masters.leave-types.create') }}" class="btn btn-primary">+ Add Leave Type</a>
    </div>
@endsection

@section('content')
    <div id="leaveTypesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Name or code...">
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Leave Type</th>
                        <th>Quota</th>
                        <th>Application Rules</th>
                        <th>Paid</th>
                        <th>Proof</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="leaveTypesTableBody">
                    <tr><td colspan="8" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top" id="leaveTypesPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="leaveTypesPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="leaveTypesPaginationList"></ul>
            </div>
        </div>
    </div>
    @vite(['resources/js/leave-types-index.js'])
@endsection
