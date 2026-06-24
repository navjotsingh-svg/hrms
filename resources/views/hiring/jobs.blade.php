@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="jobStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="jobStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="jobSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="jobSearchFilter" placeholder="Search jobs">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Employment Type</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="jobsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small" id="jobsPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="jobsPaginationList"></ul>
            </div>
        </div>
    </div>

    @if ($canManage)
    <div class="modal fade" id="jobModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="jobModalLabel">Create Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="jobForm" class="modal-body">
                    <input type="hidden" id="jobEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="jobTitle">Title *</label>
                            <input type="text" class="form-control" id="jobTitle" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="jobLocation">Location</label>
                            <input type="text" class="form-control" id="jobLocation">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="jobEmploymentType">Employment Type</label>
                            <select class="form-select" id="jobEmploymentType">
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="jobDescriptionHtml">Description (HTML)</label>
                            <textarea class="form-control font-monospace" id="jobDescriptionHtml" rows="8"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="jobForm" class="btn btn-primary">Save Job</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
