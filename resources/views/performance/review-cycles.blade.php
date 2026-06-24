@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="cycleStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="cycleStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex justify-content-end ms-auto">
                    <button type="button" class="btn btn-outline-secondary" id="cycleFilterReset">Reset</button>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Reviews Open</th>
                        <th>Pairs</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="cyclesTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($canManage)
    <div class="modal fade" id="cycleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cycleModalLabel">Create Review Cycle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="cycleForm" class="modal-body">
                    <input type="hidden" id="cycleEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="cycleName">Name *</label>
                            <input type="text" class="form-control" id="cycleName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cyclePeriodStart">Period Start *</label>
                            <input type="date" class="form-control" id="cyclePeriodStart" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cyclePeriodEnd">Period End *</label>
                            <input type="date" class="form-control" id="cyclePeriodEnd" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="cycleDescription">Description</label>
                            <textarea class="form-control" id="cycleDescription" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Questions</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addCycleQuestionBtn">+ Add Question</button>
                            </div>
                            <div id="cycleQuestionsList" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="cycleForm" class="btn btn-primary" id="cycleFormSubmitBtn">Save Cycle</button>
                </div>
            </div>
        </div>
    </div>

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
