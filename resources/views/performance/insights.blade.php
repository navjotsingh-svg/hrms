@extends('performance.layout')

@section('performance-content')
    <div class="row g-3 mb-4" id="insightsStats">
        <div class="col-md-6 col-xl-3">
            <x-hrms-stat-card label="Active Review Cycles" value-id="insightsActiveCycles" variant="brand" icon="cycle" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-hrms-stat-card label="Pending Reviews" value-id="insightsPendingReviews" variant="warning" icon="review" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-hrms-stat-card label="Active Goals" value-id="insightsActiveGoals" variant="success" icon="goal" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-hrms-stat-card label="Active PIPs" value-id="insightsActivePips" variant="danger" icon="pip" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="content-card hrms-chart-card h-100">
                <div class="content-card-body">
                    <h3 class="hrms-chart-card__title">Organization Performance</h3>
                    <p class="hrms-chart-card__subtitle">Key performance indicators across your workforce.</p>
                    <canvas id="insightsMetricsChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card hrms-chart-card h-100">
                <div class="content-card-body">
                    <h3 class="hrms-chart-card__title">Review Completion</h3>
                    <p class="hrms-chart-card__subtitle">Status distribution for current review cycles.</p>
                    <canvas id="insightsReviewStatusChart"></canvas>
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
                    @if (Auth::user()->canSeeMenu('performance.question_bank'))
                        <a href="{{ route('web.performance.question-bank') }}" class="btn btn-sm btn-outline-secondary">Question Bank</a>
                    @endif
                    <a href="{{ route('web.performance.goals') }}" class="btn btn-sm btn-outline-secondary">Goals & OKRs</a>
                    @if (Auth::user()->canSeeMenu('performance.kpi'))
                        <a href="{{ route('web.performance.kpi') }}" class="btn btn-sm btn-outline-secondary">KPI</a>
                    @endif
                </div>
            </div>
            <div class="content-card-body">
                <p class="text-muted mb-0">Configure review cycles and goals from the admin tools above.</p>
            </div>
        </div>
    @endif

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <h2 class="h5 mb-0">{{ $canManage ? 'Review Completion' : 'My Review Status' }}</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Cycle</th>
                        <th id="insightsReviewPersonHeader">{{ $canManage ? 'Reviewee' : 'Reviewer' }}</th>
                        <th>Status</th>
                        <th id="insightsReviewRatingHeader" class="{{ $canManage ? 'd-none' : '' }}">Rating</th>
                    </tr>
                </thead>
                <tbody id="insightsReviewsBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
