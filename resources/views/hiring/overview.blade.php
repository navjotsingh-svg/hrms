@extends('hiring.layout')

@section('hiring-content')
    <div class="row g-3 mb-4" id="overviewStats">
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Open Jobs</div>
                    <div class="fs-3 fw-semibold" id="statOpenJobs">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Pending Requisitions</div>
                    <div class="fs-3 fw-semibold" id="statPendingRequisitions">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active Candidates</div>
                    <div class="fs-3 fw-semibold" id="statActiveCandidates">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Upcoming Interviews</div>
                    <div class="fs-3 fw-semibold" id="statUpcomingInterviews">—</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <h2 class="h5 mb-0">Candidate Pipeline</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th class="text-end">Count</th>
                    </tr>
                </thead>
                <tbody id="pipelineTableBody">
                    <tr><td colspan="2" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
