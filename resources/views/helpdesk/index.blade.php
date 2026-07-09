@extends('layouts.app')

@section('title', 'Helpdesk - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Helpdesk</h1>
            <p class="page-subtitle mb-0">A fully digital helpdesk to raise and track tickets while HR provides resolutions and updates faster.</p>
        </div>
        @if (Auth::user()->canApplyHelpdesk())
            <a href="{{ route('web.helpdesk.create') }}" class="btn btn-primary">Raise Ticket</a>
        @endif
    </div>
@endsection

@section('content')
    <div id="helpdeskPageRoot" data-can-manage="{{ Auth::user()->canManageHelpdesk() ? '1' : '0' }}">
    <div id="helpdeskAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    @if (Auth::user()->canManageHelpdesk())
        <div class="content-card mb-4">
            <div class="content-card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="h6 mb-1">Open tickets</h2>
                    <p class="text-muted small mb-0">Tickets waiting for HR action.</p>
                </div>
                <span class="badge bg-warning text-dark fs-6" id="helpdeskOpenCount">0</span>
            </div>
        </div>
    @endif

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterCategory" class="form-label">Category</label>
                    <div class="d-flex gap-2">
                        <select class="form-select" id="filterCategory">
                            <option value="">All</option>
                        </select>
                        @if (Auth::user()->canManageHelpdesk())
                            <button type="button" class="btn btn-outline-secondary flex-shrink-0" id="helpdeskIndexAddCategoryBtn" title="Add category">+</button>
                        @endif
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="filterPriority" class="form-label">Priority</label>
                    <select class="form-select" id="filterPriority">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="search" class="form-control" id="filterSearch" placeholder="Ticket #, subject, or description">
                </div>
                <div class="col-md-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'helpdeskPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        @if (Auth::user()->canManageHelpdesk())
                            <th>Employee</th>
                        @endif
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="helpdeskTableBody">
                    <tr><td colspan="{{ Auth::user()->canManageHelpdesk() ? 8 : 7 }}" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'helpdeskPaginationInfo',
            'listId' => 'helpdeskPaginationList',
            'perPageId' => 'helpdeskPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Helpdesk pagination',
        ])
    </div>
    </div>

    @if (Auth::user()->canManageHelpdesk())
        <div class="modal fade" id="helpdeskCategoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="helpdeskCategoryForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Helpdesk Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="helpdeskCategoryName" class="form-label">Category name</label>
                            <input type="text" class="form-control" id="helpdeskCategoryName" maxlength="100" required placeholder="e.g. Benefits">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="helpdeskCategorySaveBtn">Save Category</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @vite(['resources/js/helpdesk-index.js'])
@endsection
