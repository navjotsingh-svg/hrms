@extends('layouts.app')

@section('title', 'Employee Profile - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('web.employees.index') }}">Employees</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Profile</li>
                </ol>
            </nav>
            <h1 class="page-title mb-1">Employee Profile</h1>
            <p class="page-subtitle mb-0" id="empProfilePageSubtitle">Review submitted profile details and pending approvals.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('web.employees.index') }}" class="btn btn-outline-secondary">Back to Employees</a>
            @if (Auth::user()->canReviewEmployeeDocuments())
            <a href="{{ route('web.employees.profile.edit', ['employee' => $employeeId]) }}" class="btn btn-primary btn-sm" id="empProfileManageLink">Manage Profile</a>
            @endif
            @if (Auth::user()->canManageEmployees())
            <a href="{{ route('web.employees.edit', ['employee' => $employeeId]) }}" class="table-action-btn table-action-btn--edit" id="empProfileEditLink" title="Edit employee" aria-label="Edit employee">
                @include('partials.icons.edit')
            </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="empProfileAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    @if (Auth::user()->canReviewEmployeeDocuments())
    <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4" role="status">
        <span>This page is <strong>read-only</strong> for reviewing employee submissions. To update family, address, emergency contact, bank, compliance, or documents directly, use Manage Profile.</span>
        <a href="{{ route('web.employees.profile.edit', ['employee' => $employeeId]) }}" class="btn btn-primary btn-sm flex-shrink-0">Open Manage Profile</a>
    </div>
    @endif

    <div class="profile-dashboard-grid">
        <aside class="profile-dashboard-sidebar">
            @include('employees.partials.profile-sidebar')
        </aside>

        <div class="profile-dashboard-main">
            <div id="empProfilePendingSection" class="content-card profile-page-card mb-4 d-none">
                <div class="content-card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h3 class="profile-info-card-title mb-0">Pending Approvals</h3>
                        <span class="badge text-bg-warning" id="empProfilePendingCount">0 pending</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table profile-documents-table mb-0">
                            <thead>
                                <tr>
                                    <th>Section</th>
                                    <th>Details</th>
                                    <th>Submitted</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="empProfilePendingBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="content-card profile-page-card">
        <div class="profile-tab-nav-wrap">
            <ul class="nav nav-tabs profile-tab-nav" id="empProfileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="emp-profile-work-tab" data-bs-toggle="tab" data-bs-target="#empProfileWorkPane" type="button" role="tab">Work</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-personal-tab" data-bs-toggle="tab" data-bs-target="#empProfilePersonalPane" type="button" role="tab">Personal</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-salary-tab" data-bs-toggle="tab" data-bs-target="#empProfileSalaryPane" type="button" role="tab">Salary</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-bank-tab" data-bs-toggle="tab" data-bs-target="#empProfileBankPane" type="button" role="tab">Bank</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-compliances-tab" data-bs-toggle="tab" data-bs-target="#empProfileCompliancesPane" type="button" role="tab">Compliances</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-documents-tab" data-bs-toggle="tab" data-bs-target="#empProfileDocumentsPane" type="button" role="tab">Documents</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-other-tab" data-bs-toggle="tab" data-bs-target="#empProfileOtherPane" type="button" role="tab">Other</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="emp-profile-timeline-tab" data-bs-toggle="tab" data-bs-target="#empProfileTimelinePane" type="button" role="tab">Timeline</button>
                </li>
            </ul>
        </div>

        <div class="tab-content profile-tab-content" id="empProfileTabContent">
            <div class="tab-pane fade show active" id="empProfileWorkPane" role="tabpanel">
                <div class="profile-tab-section">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="profile-info-card h-100">
                                <h4 class="profile-info-card-title">Job Details</h4>
                                <dl class="profile-dl" id="empProfileWorkJob"></dl>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="profile-info-card h-100">
                                <h4 class="profile-info-card-title">Organization</h4>
                                <dl class="profile-dl" id="empProfileWorkOrg"></dl>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="profile-info-card h-100">
                                <h4 class="profile-info-card-title">Probation</h4>
                                <dl class="profile-dl" id="empProfileWorkProbation"></dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="empProfileSalaryPane" role="tabpanel">
                @include('profile.partials.tabs.salary')
            </div>

            <div class="tab-pane fade" id="empProfilePersonalPane" role="tabpanel">
                <div class="profile-tab-section">
                    <div class="profile-info-card mb-4">
                        <h4 class="profile-info-card-title">Display Information</h4>
                        <dl class="profile-dl" id="empProfilePersonalDisplay"></dl>
                    </div>

                    <div class="profile-info-card mb-4">
                        <h4 class="profile-info-card-title">Family Members</h4>
                        <div class="table-responsive">
                            <table class="table profile-documents-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Relation</th>
                                        <th>Mobile</th>
                                        <th>Date of Birth</th>
                                        <th>Status</th>
                                        <th>Review Notes</th>
                                        <th>Submitted</th>
                                        <th>Reviewed By</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="empProfileFamilyBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="profile-info-card mb-4">
                        <h4 class="profile-info-card-title">Address</h4>
                        <div id="empProfileAddressSection"></div>
                    </div>

                    <div class="profile-info-card">
                        <h4 class="profile-info-card-title">Emergency Contact</h4>
                        <div id="empProfileEmergencySection"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="empProfileBankPane" role="tabpanel">
                <div class="profile-tab-section">
                    <div class="table-responsive">
                        <table class="table profile-documents-table mb-0">
                            <thead>
                                <tr>
                                    <th>Payment Option</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                    <th>Review Notes</th>
                                    <th>Submitted</th>
                                    <th>Reviewed By</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="empProfileBankBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="empProfileCompliancesPane" role="tabpanel">
                <div class="profile-tab-section">
                    <dl class="profile-dl mb-4" id="empProfileComplianceFlags"></dl>
                    <div class="table-responsive">
                        <table class="table profile-documents-table mb-0">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                    <th>Review Notes</th>
                                    <th>Submitted</th>
                                    <th>Reviewed By</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="empProfileCompliancesBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="empProfileDocumentsPane" role="tabpanel">
                <div class="profile-tab-section">
                    <div class="table-responsive">
                        <table class="table profile-documents-table mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Status</th>
                                    <th>Review Notes</th>
                                    <th>Uploaded</th>
                                    <th>Reviewed By</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="empProfileDocumentsBody"></tbody>
                        </table>
                    </div>
                    <div class="profile-info-card mt-4">
                        <h4 class="profile-info-card-title">Document Types</h4>
                        <div id="empProfileRequiredDocuments" class="profile-required-docs"></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="empProfileOtherPane" role="tabpanel">
                @include('profile.partials.tabs.other')
            </div>

            <div class="tab-pane fade" id="empProfileTimelinePane" role="tabpanel">
                <div class="profile-tab-section">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h4 class="profile-info-card-title mb-1">Timeline</h4>
                            <p class="text-muted small mb-0">Chronological audit of profile changes, requests, attendance, and auth events for this employee.</p>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="empProfileTimelineRefresh">Refresh</button>
                    </div>
                    <div id="empProfileTimelineList" class="activity-timeline">
                        <div class="text-muted py-4 text-center">Open this tab to load timeline entries.</div>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectProfileSubmissionModal" tabindex="-1" aria-labelledby="rejectProfileSubmissionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectProfileSubmissionModalLabel">Reject Submission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectProfileSubmissionForm">
                    <div class="modal-body">
                        <p class="text-muted small">Provide a reason so the employee knows what to fix before re-submitting.</p>
                        <label for="rejectProfileSubmissionNotes" class="form-label">Rejection reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectProfileSubmissionNotes" rows="4" required minlength="5" maxlength="1000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="rejectProfileSubmissionSubmit">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectDocumentModal" tabindex="-1" aria-labelledby="rejectDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectDocumentModalLabel">Reject Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rejectDocumentForm">
                    <div class="modal-body">
                        <p class="text-muted small">Provide a reason so the employee knows what to fix before re-uploading.</p>
                        <label for="rejectDocumentNotes" class="form-label">Rejection reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectDocumentNotes" rows="4" required minlength="5" maxlength="1000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="rejectDocumentSubmit">Reject Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="viewDocumentLightbox" class="document-lightbox d-none" role="dialog" aria-modal="true" aria-labelledby="viewDocumentLightboxTitle">
        <div class="document-lightbox-toolbar">
            <h2 class="document-lightbox-title" id="viewDocumentLightboxTitle">Document Preview</h2>
            <div class="document-lightbox-actions">
                <button type="button" class="document-lightbox-action-btn" id="viewDocumentOpenTab" title="Open in new tab">
                    <span aria-hidden="true">↗</span>
                </button>
                <button type="button" class="document-lightbox-close" id="viewDocumentLightboxClose" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
        <div class="document-lightbox-stage">
            <iframe id="viewDocumentFrame" class="document-lightbox-frame d-none" title="Document preview"></iframe>
            <img id="viewDocumentImage" class="document-lightbox-image d-none" alt="Document preview">
            <div id="viewDocumentUnsupported" class="document-lightbox-unsupported d-none">
                <p class="mb-3">This file type cannot be previewed in the browser.</p>
                <button type="button" class="btn btn-light btn-sm" id="viewDocumentFallbackDownload">Download file</button>
            </div>
        </div>
    </div>

    <script>window.EMP_PROFILE_EMPLOYEE_ID = @json($employeeId);</script>
    @vite(['resources/js/employee-profile.js'])
@endsection
