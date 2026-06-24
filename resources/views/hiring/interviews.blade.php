@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="interviewStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="interviewStatusFilter">
                        <option value="">All</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Candidate</th>
                        <th>Scheduled At</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="interviewsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small" id="interviewsPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="interviewsPaginationList"></ul>
            </div>
        </div>
    </div>

    @if ($canInterview)
    <div class="modal fade" id="interviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="interviewModalLabel">Schedule Interview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="interviewForm" class="modal-body">
                    <input type="hidden" id="interviewEditingId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="interviewCandidate">Candidate *</label>
                            <select class="form-select" id="interviewCandidate" required>
                                <option value="">Select candidate</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="interviewTitle">Title *</label>
                            <input type="text" class="form-control" id="interviewTitle" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="interviewScheduledAt">Scheduled At *</label>
                            <input type="datetime-local" class="form-control" id="interviewScheduledAt" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="interviewLocation">Location</label>
                            <input type="text" class="form-control" id="interviewLocation">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="interviewMeetingLink">Meeting Link</label>
                            <input type="url" class="form-control" id="interviewMeetingLink" placeholder="https://">
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="interviewForm" class="btn btn-primary">Save Interview</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
