@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card mb-4">
        <div class="content-card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="content-card-title mb-1">Eligible Employees</h2>
                <p class="text-muted small mb-0">System-generated promotion recommendations based on tenure, performance reviews, probation, and PIP status.</p>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="promotionCriteriaToggle">View criteria</button>
        </div>
        <div class="content-card-body border-bottom d-none" id="promotionCriteriaPanel">
            <ul class="small mb-0 ps-3" id="promotionCriteriaList"></ul>
        </div>
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="promotionRecommendSearch" class="form-label">Search eligible employees</label>
                    <input type="search" class="form-control" id="promotionRecommendSearch" placeholder="Search by name, code, or designation">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Current Role</th>
                        <th>Tenure</th>
                        <th>Latest Rating</th>
                        <th>Match</th>
                        <th>Criteria</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="promotionRecommendationsBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Loading recommendations…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-header border-bottom">
            <h2 class="content-card-title mb-1">Submitted Recommendations</h2>
            <p class="text-muted small mb-0">Formal promotion recommendations submitted for review. Endorsing a recommendation does not change the employee designation automatically.</p>
        </div>
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="promotionStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="promotionStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="nominated">Submitted</option>
                        <option value="approved">Endorsed</option>
                        <option value="rejected">Not Endorsed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="promotionSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="promotionSearchFilter" placeholder="Search recommendations">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-top', [
            'infoId' => 'promotionsPaginationInfo',
            'listId' => 'promotionsPaginationList',
            'perPageId' => 'promotionsPerPage',
            'ariaLabel' => 'Promotion recommendations pagination',
        ])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Current Role</th>
                        <th>Proposed Role</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="promotionsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'promotionsPaginationInfo',
            'listId' => 'promotionsPaginationList',
            'perPageId' => 'promotionsPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Promotion recommendations pagination',
        ])
    </div>

    <div class="modal fade" id="promotionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="promotionModalLabel">Create Promotion Recommendation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="promotionForm" class="modal-body">
                    <input type="hidden" id="promotionEditingId">
                    <div class="alert alert-info small" id="promotionEligibilityNotice">
                        Only employees who meet the predefined promotion criteria can be recommended.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            @include('partials.employee-search-select', [
                                'inputId' => 'promotionEmployeeSearch',
                                'hiddenId' => 'promotionEmployeeId',
                                'label' => 'Employee *',
                                'placeholder' => 'Search eligible employee',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promotionEffectiveDate">Target Effective Date</label>
                            <input type="date" class="form-control" id="promotionEffectiveDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promotionCurrentDesignation">Current Designation</label>
                            <input type="text" class="form-control" id="promotionCurrentDesignation" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promotionProposedDesignation">Proposed Designation *</label>
                            <input type="text" class="form-control" id="promotionProposedDesignation" required placeholder="Suggested next role">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="promotionJustification">Recommendation Notes</label>
                            <textarea class="form-control" id="promotionJustification" rows="3" placeholder="Why is this employee recommended for promotion?"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="promotionForm" class="btn btn-primary">Save Recommendation</button>
                </div>
            </div>
        </div>
    </div>
@endsection
