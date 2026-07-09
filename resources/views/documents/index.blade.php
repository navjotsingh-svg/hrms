@extends('layouts.app')

@section('title', 'Documents - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Documents</h1>
            <p class="page-subtitle mb-0">Define document types employees must or can upload.</p>
        </div>
        <a href="{{ route('web.masters.documents.create') }}" class="btn btn-primary">
            + Add Document Type
        </a>
    </div>
@endsection

@section('content')
    <div id="documentsAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Document name or code...">
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

        @include('partials.list-pagination-header', ['perPageId' => 'documentsPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Document</th>
                        <th>Code</th>
                        <th>Required</th>
                        <th>Upload</th>
                        <th>Status</th>
                        <th class="companies-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="documentsTableBody">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">Loading documents...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @include('partials.list-pagination-footer', [
            'infoId' => 'documentsPaginationInfo',
            'listId' => 'documentsPaginationList',
            'perPageId' => 'documentsPerPage',
            'wrapId' => 'documentsPagination',
            'ariaLabel' => 'Documents pagination',
            'infoText' => 'Loading pagination...',
        ])
    </div>
    @vite(['resources/js/documents-index.js'])
@endsection
