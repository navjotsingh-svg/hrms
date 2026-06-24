@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="candidateStageFilter" class="form-label">Stage</label>
                    <select class="form-select" id="candidateStageFilter">
                        <option value="">All</option>
                        <option value="applied">Applied</option>
                        <option value="screening">Screening</option>
                        <option value="interview">Interview</option>
                        <option value="offer">Offer</option>
                        <option value="hired">Hired</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="candidateSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="candidateSearchFilter" placeholder="Search candidates">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Job</th>
                        <th>Source</th>
                        <th>Stage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="candidatesTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small" id="candidatesPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="candidatesPaginationList"></ul>
            </div>
        </div>
    </div>

    @if ($canManage)
    <div class="modal fade" id="candidateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="candidateModalLabel">Add Candidate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="candidateForm" class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="candidateFirstName">First Name *</label>
                            <input type="text" class="form-control" id="candidateFirstName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="candidateLastName">Last Name *</label>
                            <input type="text" class="form-control" id="candidateLastName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="candidateEmail">Email *</label>
                            <input type="email" class="form-control" id="candidateEmail" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="candidatePhone">Phone</label>
                            <input type="text" class="form-control" id="candidatePhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="candidateJob">Job</label>
                            <select class="form-select" id="candidateJob">
                                <option value="">Select job</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="candidateSource">Source</label>
                            <input type="text" class="form-control" id="candidateSource" placeholder="referral, linkedin, etc.">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="candidateNotes">Notes</label>
                            <textarea class="form-control" id="candidateNotes" rows="3"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="candidateForm" class="btn btn-primary">Add Candidate</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
