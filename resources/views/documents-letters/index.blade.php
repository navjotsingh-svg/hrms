@extends('layouts.app')

@section('title', 'Documents & Letters - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Documents & Letters</h1>
            <p class="page-subtitle mb-0">Generate documents related to employee onboarding, compliance, policies and store all documents shared by employees in one place.</p>
        </div>
        @if ($canManage)
            <button type="button" class="btn btn-primary" id="docLettersIssueBtn">Issue Document</button>
        @endif
    </div>
@endsection

@section('content')
    <div id="docLettersPageRoot"
         data-can-manage="{{ $canManage ? '1' : '0' }}"
         data-show-url="{{ url('/documents-letters') }}">
        <div id="docLettersAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

        <div class="content-card mb-4 d-none" id="docLettersPendingCard">
            <div class="content-card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="h6 mb-1">Awaiting your signature</h2>
                    <p class="text-muted small mb-0">Review and sign documents issued to you.</p>
                </div>
                <span class="badge bg-warning text-dark fs-6" id="docLettersPendingCount">0</span>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" id="docLettersTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-letters" data-bs-toggle="tab" data-bs-target="#pane-letters" type="button" role="tab">Issued Letters</button>
            </li>
            @if ($canManage)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-templates" data-bs-toggle="tab" data-bs-target="#pane-templates" type="button" role="tab">Templates</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-uploads" data-bs-toggle="tab" data-bs-target="#pane-uploads" type="button" role="tab">Employee Uploads</button>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-letters" role="tabpanel">
                <div class="content-card companies-list-card">
                    <div class="content-card-body companies-filter-bar border-bottom">
                        <div class="row g-3 align-items-end">
                            @if ($canManage)
                                <div class="col-md-3">
                                    @include('partials.employee-search-select', [
                                        'inputId' => 'filterEmployeeSearch',
                                        'hiddenId' => 'filterEmployeeId',
                                        'label' => 'Employee',
                                        'placeholder' => 'All employees',
                                    ])
                                </div>
                            @endif
                            <div class="col-md-2">
                                <label for="filterLetterStatus" class="form-label">Status</label>
                                <select class="form-select" id="filterLetterStatus">
                                    <option value="">All</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filterLetterCategory" class="form-label">Category</label>
                                <select class="form-select" id="filterLetterCategory">
                                    <option value="">All</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filterLetterSearch" class="form-label">Search</label>
                                <input type="search" class="form-control" id="filterLetterSearch" placeholder="Title or document number">
                            </div>
                            <div class="col-md-2 d-flex justify-content-end">
                                <button type="button" class="btn btn-outline-secondary" id="filterLetterReset">Reset</button>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="companies-table table mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    @if ($canManage)
                                        <th>Employee</th>
                                    @endif
                                    <th>Issued</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="docLettersTableBody">
                                <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-muted py-5">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="content-card-body border-top">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="small text-muted" id="docLettersPaginationInfo"></div>
                            <ul class="pagination pagination-sm mb-0" id="docLettersPaginationList"></ul>
                        </div>
                    </div>
                </div>
            </div>

            @if ($canManage)
                <div class="tab-pane fade" id="pane-templates" role="tabpanel">
                    <div class="d-flex justify-content-end mb-3">
                        <button type="button" class="btn btn-primary btn-sm" id="docLettersCreateTemplateBtn">Create Template</button>
                    </div>
                    <div class="content-card companies-list-card">
                        <div class="content-card-body companies-filter-bar border-bottom">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="filterTemplateCategory" class="form-label">Category</label>
                                    <select class="form-select" id="filterTemplateCategory">
                                        <option value="">All</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="filterTemplateStatus" class="form-label">Status</label>
                                    <select class="form-select" id="filterTemplateStatus">
                                        <option value="">All</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filterTemplateSearch" class="form-label">Search</label>
                                    <input type="search" class="form-control" id="filterTemplateSearch" placeholder="Template name">
                                </div>
                                <div class="col-md-2 d-flex justify-content-end">
                                    <button type="button" class="btn btn-outline-secondary" id="filterTemplateReset">Reset</button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="companies-table table mb-0">
                                <thead>
                                    <tr>
                                        <th>Template</th>
                                        <th>Category</th>
                                        <th>Signature</th>
                                        <th>Status</th>
                                        <th>Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="docTemplatesTableBody">
                                    <tr><td colspan="6" class="text-center text-muted py-5">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="content-card-body border-top">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="small text-muted" id="docTemplatesPaginationInfo"></div>
                                <ul class="pagination pagination-sm mb-0" id="docTemplatesPaginationList"></ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="pane-uploads" role="tabpanel">
                    <div class="content-card companies-list-card">
                        <div class="content-card-body border-bottom">
                            <p class="text-muted small mb-0">Pending employee document uploads awaiting HR review.</p>
                        </div>
                        <div class="table-responsive">
                            <table class="companies-table table mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Document Type</th>
                                        <th>File</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="docUploadsTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-5">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($canManage)
        <div class="modal fade" id="docLettersIssueModal" tabindex="-1" aria-labelledby="docLettersIssueModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="docLettersIssueForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="docLettersIssueModalLabel">Issue Document</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    @include('partials.employee-search-select', [
                                        'inputId' => 'issueEmployeeSearch',
                                        'hiddenId' => 'issueEmployeeId',
                                        'label' => 'Employee',
                                        'required' => true,
                                    ])
                                </div>
                                <div class="col-md-6">
                                    <label for="issueTemplateId" class="form-label">Template</label>
                                    <select class="form-select" id="issueTemplateId">
                                        <option value="">Custom content</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="issueTitle" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="issueTitle" required maxlength="255">
                                </div>
                                <div class="col-md-4">
                                    <label for="issueCategory" class="form-label">Category</label>
                                    <select class="form-select" id="issueCategory"></select>
                                </div>
                                <div class="col-md-6">
                                    <label for="issueSalary" class="form-label">Salary / CTC</label>
                                    <input type="text" class="form-control" id="issueSalary" maxlength="100" placeholder="Optional — fills {salary}">
                                </div>
                                <div class="col-md-6">
                                    <label for="issueJoiningDate" class="form-label">Joining Date</label>
                                    <input type="text" class="form-control" id="issueJoiningDate" maxlength="100" placeholder="Optional — fills {joining_date}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Document body</label>
                                    <div id="issueBodyEditor" class="doc-letter-editor"></div>
                                    <textarea class="d-none" id="issueBodyHtml" aria-hidden="true"></textarea>
                                    <div class="form-text mt-2" id="issuePlaceholderHelp"></div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="issueRequiresSignature" checked>
                                        <label class="form-check-label" for="issueRequiresSignature">Requires employee signature</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="issueNow" checked>
                                        <label class="form-check-label" for="issueNow">Issue immediately (send to employee)</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Preview</label>
                                    <div class="border rounded p-3 bg-light" id="issuePreviewBox">
                                        <span class="text-muted">Select an employee and template to preview.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" id="issuePreviewBtn">Refresh Preview</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Issue Document</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="docLettersTemplateModal" tabindex="-1" aria-labelledby="docLettersTemplateModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="docLettersTemplateForm">
                        <input type="hidden" id="templateEditId">
                        <div class="modal-header">
                            <h5 class="modal-title" id="docLettersTemplateModalLabel">Document Template</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label for="templateName" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="templateName" required maxlength="255">
                                </div>
                                <div class="col-md-4">
                                    <label for="templateCategory" class="form-label">Category</label>
                                    <select class="form-select" id="templateCategory" required></select>
                                </div>
                                <div class="col-md-8">
                                    <label for="templateSubject" class="form-label">Subject (optional)</label>
                                    <input type="text" class="form-control" id="templateSubject" maxlength="255">
                                </div>
                                <div class="col-md-4">
                                    <label for="templateStatus" class="form-label">Status</label>
                                    <select class="form-select" id="templateStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <div id="templateDescriptionEditor" class="doc-letter-editor doc-letter-editor--compact"></div>
                                    <textarea class="d-none" id="templateDescription" maxlength="2000" aria-hidden="true"></textarea>
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                                    <span class="small text-muted">Quick start:</span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-sample-template="offer_letter">Use Offer Letter Sample</button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Letter body</label>
                                    <div id="templateBodyEditor" class="doc-letter-editor"></div>
                                    <textarea class="d-none" id="templateBodyHtml" required aria-hidden="true"></textarea>
                                    <div class="form-text mt-2" id="templatePlaceholderHelp"></div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="templateRequiresSignature" checked>
                                        <label class="form-check-label" for="templateRequiresSignature">Requires employee signature</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="templateIsDefault">
                                        <label class="form-check-label" for="templateIsDefault">Default template for this category</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Template</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @vite(['resources/js/documents-letters-index.js'])
@endsection
