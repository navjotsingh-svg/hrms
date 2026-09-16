@extends('layouts.app')

@php($canManage = $canManage ?? false)

@section('title', 'Company Policies - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Company Policies</h1>
            <p class="page-subtitle mb-0">
                {{ $canManage
                    ? 'Upload and publish company policies such as Code of Conduct for employees to view.'
                    : 'View company policies shared by HR.' }}
            </p>
        </div>
        @if ($canManage)
            <button type="button" class="btn btn-primary" id="companyPolicyUploadBtn">+ Upload Policy</button>
        @endif
    </div>
@endsection

@section('content')
    <div id="companyPoliciesAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card" id="companyPoliciesRoot" data-can-manage="{{ $canManage ? '1' : '0' }}">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Policy title...">
                </div>
                <div class="col-md-3">
                    <label for="filterCategory" class="form-label">Category</label>
                    <select class="form-select" id="filterCategory">
                        <option value="">All categories</option>
                    </select>
                </div>
                @if ($canManage)
                    <div class="col-md-3">
                        <label for="filterStatus" class="form-label">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                @endif
                <div class="col-md-2 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        @include('partials.list-pagination-top', [
            'infoId' => 'companyPoliciesPaginationInfo',
            'listId' => 'companyPoliciesPaginationList',
            'perPageId' => 'companyPoliciesPerPage',
            'wrapId' => 'companyPoliciesPagination',
            'ariaLabel' => 'Company policies pagination',
        ])

        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Policy</th>
                        <th>Category</th>
                        <th>File</th>
                        <th>Updated</th>
                        @if ($canManage)
                            <th>Status</th>
                            <th class="companies-th-actions">Actions</th>
                        @else
                            <th class="companies-th-actions">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="companyPoliciesTableBody">
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-muted py-5">Loading policies...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade" id="companyPolicyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" id="companyPolicyForm" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="companyPolicyModalTitle">Upload Policy</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="companyPolicyId" value="">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="companyPolicyTitle" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="companyPolicyTitle" required maxlength="255" placeholder="e.g. Code of Conduct">
                            </div>
                            <div class="col-md-4">
                                <label for="companyPolicyCategory" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="companyPolicyCategory" required></select>
                            </div>
                            <div class="col-12">
                                <label for="companyPolicyDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="companyPolicyDescription" rows="3" maxlength="2000" placeholder="Optional short summary"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="companyPolicyStatus" class="form-label">Status</label>
                                <select class="form-select" id="companyPolicyStatus">
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="companyPolicyFile" class="form-label">File <span class="text-danger" id="companyPolicyFileRequired">*</span></label>
                                <input type="file" class="form-control" id="companyPolicyFile">
                                <div class="form-text" id="companyPolicyFileHelp">PDF, Word, Excel, or image up to 10 MB.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="companyPolicySaveBtn">Save Policy</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @vite(['resources/js/company-policies-index.js'])
@endpush
