@extends('layouts.app')

@section('title', 'Assets - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Assets</h1>
            <p class="page-subtitle mb-0">Define asset types that can be assigned to employees.</p>
        </div>
        <a href="{{ route('web.masters.assets.create') }}" class="btn btn-primary">
            + Add Asset
        </a>
    </div>
@endsection

@section('content')
    <div id="assetsAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Asset name...">
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
                        <th class="companies-th-serial">#</th>
                        <th>Asset</th>
                        <th>Status</th>
                        <th class="companies-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="assetsTableBody">
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">Loading assets...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="content-card-body border-top companies-pagination-footer" id="assetsPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="assetsPaginationInfo">Loading pagination...</div>
                <nav aria-label="Assets pagination">
                    <ul class="pagination pagination-sm mb-0" id="assetsPaginationList"></ul>
                </nav>
            </div>
        </div>
    </div>
    @vite(['resources/js/assets-index.js'])
@endsection
