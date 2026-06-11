@extends('layouts.app')

@section('title', 'Companies - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Companies</h1>
            <p class="page-subtitle mb-0">Manage companies registered on the HRMS platform.</p>
        </div>
        <a href="{{ route('web.companies.create') }}" class="btn btn-primary">
            + Add Company
        </a>
    </div>
@endsection

@section('content')
    <div id="companiesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="companies-filter-header">
                <div>
                    <span class="companies-filter-title">Search &amp; filter</span>
                    <span class="companies-filter-hint">Pick a company or city from suggestions to filter the table</span>
                </div>
                <div id="companiesFilterLoading" class="companies-filter-loading d-none" aria-live="polite">
                    <span class="companies-filter-loading-spinner" aria-hidden="true"></span>
                    <span>Updating...</span>
                </div>
            </div>
            <div id="companiesFilterForm" class="row g-3 align-items-end">
                <div class="col-lg-5 col-md-6">
                    <label for="filterName" class="form-label">Company Name</label>
                    <div class="filter-autocomplete">
                        <input type="text" class="form-control" id="filterName" placeholder="Type to search, then pick from list..." autocomplete="off">
                        <div id="filterNameSuggestions" class="filter-autocomplete-menu d-none" role="listbox"></div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-3">
                    <label for="filterCity" class="form-label">City</label>
                    <div class="filter-autocomplete">
                        <input type="text" class="form-control" id="filterCity" placeholder="Type to search, then pick from list..." autocomplete="off">
                        <div id="filterCitySuggestions" class="filter-autocomplete-menu d-none" role="listbox"></div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-12 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div class="companies-stats-bar" id="companiesStatsBar">
            <div class="companies-stat companies-stat--total">
                <span class="companies-stat-value" id="companiesStatTotal">—</span>
                <span class="companies-stat-label">Total companies</span>
            </div>
            <div class="companies-stat companies-stat--active">
                <span class="companies-stat-value" id="companiesStatActive">—</span>
                <span class="companies-stat-label">Active</span>
            </div>
            <div class="companies-stat companies-stat--inactive">
                <span class="companies-stat-value" id="companiesStatInactive">—</span>
                <span class="companies-stat-label">Inactive</span>
            </div>
        </div>

        <div class="companies-table-wrap" id="companiesTableWrap">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Company</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th class="companies-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody
                    id="companiesTableBody"
                    data-success-message="{{ request()->query('success') ? urldecode(request()->query('success')) : '' }}"
                >
                    <tr>
                        <td colspan="6">
                            <div class="companies-loading-state">
                                <div class="companies-loading-spinner" role="status" aria-label="Loading"></div>
                                <span>Loading companies...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="content-card-body border-top companies-pagination-footer" id="companiesPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="companiesPaginationInfo">Loading pagination...</div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="companies-per-page">
                        <label for="companiesPerPage" class="companies-per-page-label">Per page</label>
                        <select id="companiesPerPage" class="form-select form-select-sm companies-per-page-select">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <nav aria-label="Companies pagination">
                        <ul class="pagination pagination-sm mb-0" id="companiesPaginationList"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    @vite(['resources/js/companies-index.js'])
@endsection
