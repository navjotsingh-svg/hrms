@extends('performance.layout')

@section('performance-content')
    <div class="row g-3 mb-4" id="insightsStats">
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active Review Cycles</div>
                    <div class="fs-3 fw-semibold" id="insightsActiveCycles">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Pending Reviews</div>
                    <div class="fs-3 fw-semibold" id="insightsPendingReviews">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active Goals</div>
                    <div class="fs-3 fw-semibold" id="insightsActiveGoals">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active PIPs</div>
                    <div class="fs-3 fw-semibold" id="insightsActivePips">—</div>
                </div>
            </div>
        </div>
    </div>

    @if ($canManage)
        <div class="content-card companies-list-card mb-3">
            <div class="content-card-body border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h5 mb-0">Admin Tools</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('web.performance.review-cycles') }}" class="btn btn-sm btn-outline-secondary">Review Cycles</a>
                    <a href="{{ route('web.performance.question-bank') }}" class="btn btn-sm btn-outline-secondary">Question Bank</a>
                    <a href="{{ route('web.performance.goals') }}" class="btn btn-sm btn-outline-secondary">Goals & OKRs</a>
                    <a href="{{ route('web.performance.kpi') }}" class="btn btn-sm btn-outline-secondary">KPI</a>
                </div>
            </div>
            <div class="content-card-body">
                <p class="text-muted mb-0">Configure review cycles, goals, KPIs, and question banks from the admin tools above.</p>
            </div>
        </div>
    @endif

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <h2 class="h5 mb-0">Review Completion</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Cycle</th>
                        <th>Reviewee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="insightsReviewsBody">
                    <tr><td colspan="3" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
