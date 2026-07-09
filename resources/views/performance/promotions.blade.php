@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="promotionStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="promotionStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="nominated">Nominated</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="promotionSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="promotionSearchFilter" placeholder="Search promotions">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'promotionsPerPage'])
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
            'ariaLabel' => 'Promotions pagination',
        ])
    </div>

    <div class="modal fade" id="promotionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="promotionModalLabel">Create Promotion Nomination</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="promotionForm" class="modal-body">
                    <input type="hidden" id="promotionEditingId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            @include('partials.employee-search-select', [
                                'inputId' => 'promotionEmployeeSearch',
                                'hiddenId' => 'promotionEmployeeId',
                                'label' => 'Employee *',
                                'placeholder' => 'Search employee',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promotionEffectiveDate">Effective Date</label>
                            <input type="date" class="form-control" id="promotionEffectiveDate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promotionCurrentDesignation">Current Designation</label>
                            <input type="text" class="form-control" id="promotionCurrentDesignation">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="promotionProposedDesignation">Proposed Designation *</label>
                            <input type="text" class="form-control" id="promotionProposedDesignation" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="promotionJustification">Justification</label>
                            <textarea class="form-control" id="promotionJustification" rows="3"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="promotionForm" class="btn btn-primary">Save Nomination</button>
                </div>
            </div>
        </div>
    </div>
@endsection
