@extends('layouts.app')

@section('title', 'Holidays - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Holidays</h1>
            <p class="page-subtitle mb-0">Manage company holidays shown on the attendance calendar.</p>
        </div>
        <a href="{{ route('web.masters.attendance.holidays.create') }}" class="btn btn-primary">
            + Add Holiday
        </a>
    </div>
@endsection

@section('content')
    <div id="holidaysAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Holiday name...">
                </div>
                <div class="col-md-3">
                    <label for="filterYear" class="form-label">Year</label>
                    <select class="form-select" id="filterYear"></select>
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Holiday</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="companies-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="holidaysTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">Loading holidays...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="content-card-body border-top companies-pagination-footer" id="holidaysPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="holidaysPaginationInfo">Loading pagination...</div>
                <nav aria-label="Holidays pagination">
                    <ul class="pagination pagination-sm mb-0" id="holidaysPaginationList"></ul>
                </nav>
            </div>
        </div>
    </div>
    @vite(['resources/js/holidays-index.js'])
@endsection
