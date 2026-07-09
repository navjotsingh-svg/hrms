@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="formStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="formStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="formSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="formSearchFilter" placeholder="Search forms">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'feedbackFormsPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Questions</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="feedbackFormsTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'feedbackFormsPaginationInfo',
            'listId' => 'feedbackFormsPaginationList',
            'perPageId' => 'feedbackFormsPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Feedback forms pagination',
        ])
    </div>

    @if ($canManage)
    <div class="modal fade" id="feedbackFormModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="feedbackFormModalLabel">Create Feedback Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="feedbackFormForm" class="modal-body">
                    <input type="hidden" id="feedbackFormEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="feedbackFormName">Name *</label>
                            <input type="text" class="form-control" id="feedbackFormName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="feedbackFormStatus">Status</label>
                            <select class="form-select" id="feedbackFormStatus">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="feedbackFormDescription">Description</label>
                            <textarea class="form-control" id="feedbackFormDescription" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Questions</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addFeedbackQuestionBtn">+ Add Question</button>
                            </div>
                            <div id="feedbackQuestionsList" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="feedbackFormForm" class="btn btn-primary">Save Form</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
