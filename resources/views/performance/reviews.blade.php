@extends('performance.layout')

@section('performance-content')
    <ul class="nav nav-tabs mb-3" id="performanceReviewsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="my-reviews-tab" data-bs-toggle="tab" data-bs-target="#my-reviews-pane" type="button" role="tab">My Reviews</button>
        </li>
        @if ($canManage)
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="review-cycles-tab" data-bs-toggle="tab" data-bs-target="#review-cycles-pane" type="button" role="tab">Review Cycles</button>
        </li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="my-reviews-pane" role="tabpanel">
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

            <div class="content-card companies-list-card">
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

            <div class="content-card companies-list-card mt-4">
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
        </div>

        @if ($canManage)
        <div class="tab-pane fade" id="review-cycles-pane" role="tabpanel">
            @include('performance.partials.review-cycles-panel')
        </div>
        @endif
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
