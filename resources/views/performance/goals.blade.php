@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="goalStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="goalStatusFilter">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="goalSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="goalSearchFilter" placeholder="Search goals">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'goalsPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="goalsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
            @include('partials.list-pagination-footer', [
                'infoId' => 'goalsPaginationInfo',
                'listId' => 'goalsPaginationList',
                'perPageId' => 'goalsPerPage',
                'wrapClass' => 'content-card-body border-top',
                'ariaLabel' => 'Goals pagination',
            ])
    </div>

    <div class="modal fade" id="goalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="goalModalLabel">Create Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="goalForm" class="modal-body">
                    <input type="hidden" id="goalEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="goalTitle">Title *</label>
                            <input type="text" class="form-control" id="goalTitle" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalStatus">Status</label>
                            <select class="form-select" id="goalStatus">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="goalDescription">Description</label>
                            <textarea class="form-control" id="goalDescription" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalPeriodStart">Period Start</label>
                            <input type="date" class="form-control" id="goalPeriodStart">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalPeriodEnd">Period End</label>
                            <input type="date" class="form-control" id="goalPeriodEnd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalVisibility">Visibility</label>
                            <select class="form-select" id="goalVisibility">
                                <option value="team">Team</option>
                                <option value="private">Private</option>
                                <option value="company">Company</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Key Results</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addKeyResultBtn">+ Add Key Result</button>
                            </div>
                            <div id="keyResultsList" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="goalForm" class="btn btn-primary">Save Goal</button>
                </div>
            </div>
        </div>
    </div>
@endsection
