@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="pipStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="pipStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="pipSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="pipSearchFilter" placeholder="Search PIPs">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'pipPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Employee</th>
                        <th>Manager</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="pipTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
            @include('partials.list-pagination-footer', [
                'infoId' => 'pipPaginationInfo',
                'listId' => 'pipPaginationList',
                'perPageId' => 'pipPerPage',
                'wrapClass' => 'content-card-body border-top',
                'ariaLabel' => 'PIP pagination',
            ])
    </div>

    @if ($canManagePips)
    <div class="modal fade" id="pipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pipModalLabel">Create PIP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="pipForm" class="modal-body">
                    <input type="hidden" id="pipEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="pipTitle">Title *</label>
                            <input type="text" class="form-control" id="pipTitle" required>
                        </div>
                        <div class="col-md-4">
                            @include('partials.employee-search-select', [
                                'inputId' => 'pipEmployeeSearch',
                                'hiddenId' => 'pipEmployeeId',
                                'label' => 'Employee *',
                                'placeholder' => 'Search employee',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pipReason">Reason</label>
                            <textarea class="form-control" id="pipReason" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pipStartDate">Start Date *</label>
                            <input type="date" class="form-control" id="pipStartDate" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="pipEndDate">End Date *</label>
                            <input type="date" class="form-control" id="pipEndDate" required>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Key Results / Milestones</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addPipKrBtn">+ Add Milestone</button>
                            </div>
                            <div id="pipKeyResultsList" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="pipForm" class="btn btn-primary">Save PIP</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
