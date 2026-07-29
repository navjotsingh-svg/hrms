@extends('performance.layout')

@section('performance-content')
    @if ($canManage)
    <div class="content-card mb-3">
        <div class="content-card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="text-muted small">Set up forms and questions before requesting feedback from employees.</div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('web.performance.feedback-forms') }}" class="btn btn-sm btn-outline-secondary">Feedback Forms</a>
                @if (Auth::user()->canSeeMenu('performance.question_bank'))
                    <a href="{{ route('web.performance.question-bank') }}" class="btn btn-sm btn-outline-secondary">Question Bank</a>
                @endif
            </div>
        </div>
    </div>
    @endif

    <ul class="nav nav-tabs mb-3" id="continuousFeedbackTabs" role="tablist">
        @if ($canReview)
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="give-feedback-tab" data-bs-toggle="tab" data-bs-target="#give-feedback-pane" type="button" role="tab">Give Feedback</button>
        </li>
        @endif
        <li class="nav-item" role="presentation">
            <button class="nav-link @if (!$canReview) active @endif" id="about-me-tab" data-bs-toggle="tab" data-bs-target="#about-me-pane" type="button" role="tab">Feedback About Me</button>
        </li>
        @if ($canManage)
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="all-requests-tab" data-bs-toggle="tab" data-bs-target="#all-requests-pane" type="button" role="tab">All Requests</button>
        </li>
        @endif
    </ul>

    <div class="tab-content" id="continuousFeedbackTabContent">
        @if ($canReview)
        <div class="tab-pane fade show active" id="give-feedback-pane" role="tabpanel">
            <div class="content-card companies-list-card">
                @include('partials.list-pagination-top', [
    'infoId' => 'giveFeedbackPaginationInfo',
    'listId' => 'giveFeedbackPaginationList',
    'perPageId' => 'giveFeedbackPerPage',
    'ariaLabel' => 'Give feedback pagination',
])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th>About</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="giveFeedbackTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'giveFeedbackPaginationInfo',
                    'listId' => 'giveFeedbackPaginationList',
                    'perPageId' => 'giveFeedbackPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Give feedback pagination',
                ])
            </div>
        </div>
        @endif

        <div class="tab-pane fade @if (!$canReview) show active @endif" id="about-me-pane" role="tabpanel">
            <div class="content-card companies-list-card">
                @include('partials.list-pagination-top', [
    'infoId' => 'receivedFeedbackPaginationInfo',
    'listId' => 'receivedFeedbackPaginationList',
    'perPageId' => 'receivedFeedbackPerPage',
    'ariaLabel' => 'Received feedback pagination',
])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th>From</th>
                                <th>Rating</th>
                                <th>Submitted</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="receivedFeedbackTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'receivedFeedbackPaginationInfo',
                    'listId' => 'receivedFeedbackPaginationList',
                    'perPageId' => 'receivedFeedbackPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Received feedback pagination',
                ])
            </div>
        </div>

        @if ($canManage)
        <div class="tab-pane fade" id="all-requests-pane" role="tabpanel">
            <div class="content-card companies-list-card">
                <div class="content-card-body companies-filter-bar border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="allRequestsStatusFilter" class="form-label">Status</label>
                            <select class="form-select" id="allRequestsStatusFilter">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="submitted">Submitted</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                @include('partials.list-pagination-top', [
    'infoId' => 'allRequestsPaginationInfo',
    'listId' => 'allRequestsPaginationList',
    'perPageId' => 'allRequestsPerPage',
    'ariaLabel' => 'All requests pagination',
])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th>About</th>
                                <th>Reviewer</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="allRequestsTableBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'allRequestsPaginationInfo',
                    'listId' => 'allRequestsPaginationList',
                    'perPageId' => 'allRequestsPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'All requests pagination',
                ])
            </div>
        </div>
        @endif
    </div>

    @if ($canManage)
    <div class="modal fade" id="assignFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignFeedbackForm" class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="assignFeedbackFormId">Feedback Form *</label>
                            <select class="form-select" id="assignFeedbackFormId" required>
                                <option value="">Select an active form…</option>
                            </select>
                            <div class="form-text">Only active forms with questions can be assigned.</div>
                        </div>
                        <div class="col-md-6">
                            @include('partials.employee-search-select', [
                                'inputId' => 'assignSubjectSearch',
                                'hiddenId' => 'assignSubjectEmployeeId',
                                'label' => 'Feedback About (Employee) *',
                                'placeholder' => 'Search employee…',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="assignDueDate">Due Date</label>
                            <input type="date" class="form-control" id="assignDueDate">
                        </div>
                        <div class="col-12">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-9">
                                    @include('partials.employee-search-select', [
                                        'inputId' => 'assignReviewerSearch',
                                        'hiddenId' => 'assignReviewerEmployeeId',
                                        'label' => 'Add Reviewer *',
                                        'placeholder' => 'Search employee…',
                                    ])
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-secondary w-100" id="addReviewerBtn">Add Reviewer</button>
                                </div>
                            </div>
                            <div id="assignReviewersList" class="d-flex flex-wrap gap-2 mt-2"></div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="assignContextNotes">Context / Instructions</label>
                            <textarea class="form-control" id="assignContextNotes" rows="2" placeholder="Optional note for reviewers"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="assignFeedbackForm" class="btn btn-primary">Send Requests</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="modal fade" id="feedbackResponseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackResponseModalTitle">Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="feedbackResponseForm" class="modal-body">
                    <input type="hidden" id="feedbackRequestEditingId">
                    <div id="feedbackResponseMeta" class="mb-3 text-muted small"></div>
                    <div id="feedbackContextNotes" class="alert alert-info d-none mb-3"></div>
                    <div id="feedbackQuestionsContainer" class="d-flex flex-column gap-3"></div>
                </form>
                <div class="modal-footer" id="feedbackResponseFooter">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="feedbackResponseForm" class="btn btn-primary" id="feedbackResponseSubmitBtn">Submit Feedback</button>
                </div>
            </div>
        </div>
    </div>
@endsection
