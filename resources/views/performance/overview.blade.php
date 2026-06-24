@extends('performance.layout')

@section('performance-content')
    <div class="row g-3 mb-4" id="overviewStats">
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active Review Cycles</div>
                    <div class="fs-3 fw-semibold" id="statActiveCycles">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Pending Reviews</div>
                    <div class="fs-3 fw-semibold" id="statPendingReviews">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active Goals</div>
                    <div class="fs-3 fw-semibold" id="statActiveGoals">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="text-muted small">Active PIPs</div>
                    <div class="fs-3 fw-semibold" id="statActivePips">—</div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card companies-list-card">
        <div class="content-card-body border-bottom">
            <h2 class="h5 mb-0">My Pending Reviews</h2>
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

    @if ($canReview)
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
                        <label class="form-label" for="reviewSummaryNotes">Summary Notes</label>
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
