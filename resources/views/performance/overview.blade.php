@extends('performance.layout')

@section('performance-content')
    <div class="row g-3 mb-4" id="overviewStats">
        <div class="col-md-6 col-xl">
            <x-hrms-stat-card label="Active Review Cycles" value-id="statActiveCycles" variant="brand" icon="cycle" />
        </div>
        <div class="col-md-6 col-xl">
            <x-hrms-stat-card label="Pending Reviews" value-id="statPendingReviews" variant="warning" icon="review" />
        </div>
        @if ($canReview)
        <div class="col-md-6 col-xl">
            <x-hrms-stat-card label="Pending Feedback" value-id="statPendingFeedback" variant="purple" icon="feedback" />
        </div>
        @endif
        <div class="col-md-6 col-xl">
            <x-hrms-stat-card label="Active Goals" value-id="statActiveGoals" variant="success" icon="goal" />
        </div>
        <div class="col-md-6 col-xl">
            <x-hrms-stat-card label="Active PIPs" value-id="statActivePips" variant="danger" icon="pip" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="content-card hrms-chart-card h-100">
                <div class="content-card-body">
                    <h3 class="hrms-chart-card__title">Performance Snapshot</h3>
                    <p class="hrms-chart-card__subtitle">Active cycles, reviews, goals, and improvement plans at a glance.</p>
                    <canvas id="overviewMetricsChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card hrms-chart-card h-100">
                <div class="content-card-body">
                    <h3 class="hrms-chart-card__title">Review Status</h3>
                    <p class="hrms-chart-card__subtitle">Breakdown of assigned review progress.</p>
                    <canvas id="overviewReviewStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    @if ($canReview)
    <div class="content-card companies-list-card mb-4">
        <div class="content-card-body border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0">My Pending Feedback</h2>
            <a href="{{ route('web.performance.continuous-feedback') }}" class="btn btn-sm btn-outline-primary">Open Continuous Feedback</a>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Form</th>
                        <th>About</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="overviewFeedbackBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="content-card companies-list-card mb-4">
        <div class="content-card-body border-bottom">
            <h2 class="h5 mb-0">Reviews to Complete</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Cycle</th>
                        <th>Reviewee</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="overviewReviewsBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <h2 class="h5 mb-0">Reviews About Me</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Cycle</th>
                        <th>Reviewer</th>
                        <th>Status</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody id="overviewReviewsAboutMeBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($canReview || $canParticipate)
    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reviewForm" class="modal-body">
                    <input type="hidden" id="reviewEditingId">
                    <div id="reviewMeta" class="mb-3 text-muted small"></div>
                    <div id="reviewQuestionsContainer" class="d-flex flex-column gap-3"></div>
                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <label class="form-label mb-0" for="reviewSummaryNotes">Summary Notes</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="reviewAiSuggestBtn">AI suggest</button>
                        </div>
                        <textarea class="form-control" id="reviewSummaryNotes" rows="3"></textarea>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="reviewForm" class="btn btn-primary">Submit Review</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
