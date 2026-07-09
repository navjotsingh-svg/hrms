@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="calibrationStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="calibrationStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="in_progress">In Progress</option>
                        <option value="finalized">Finalized</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="calibrationSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="calibrationSearchFilter" placeholder="Search sessions">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'calibrationPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Review Cycle</th>
                        <th>Entries</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="calibrationTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'calibrationPaginationInfo',
            'listId' => 'calibrationPaginationList',
            'perPageId' => 'calibrationPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Calibration pagination',
        ])
    </div>

    @if ($canManage)
    <div class="modal fade" id="calibrationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Calibration Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="calibrationForm" class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="calibrationName">Session Name *</label>
                        <input type="text" class="form-control" id="calibrationName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="calibrationCycleId">Review Cycle</label>
                        <select class="form-select" id="calibrationCycleId">
                            <option value="">No cycle (manual session)</option>
                        </select>
                        <div class="form-text">When linked, manager review ratings are imported automatically.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="calibrationDescription">Description</label>
                        <textarea class="form-control" id="calibrationDescription" rows="2"></textarea>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="calibrationForm" class="btn btn-primary">Create Session</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="calibrationDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="calibrationDetailTitle">Calibration Session</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0" id="calibrationDetailMeta"></p>
                        <button type="button" class="btn btn-success btn-sm d-none" id="finalizeCalibrationBtn">Finalize Session</button>
                    </div>
                    <div class="table-responsive">
                        <table class="companies-table table mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Original Rating</th>
                                    <th>Calibrated Rating</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="calibrationEntriesBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
