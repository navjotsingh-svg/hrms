@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="qbCategoryFilter" class="form-label">Category</label>
                    <input type="text" class="form-control" id="qbCategoryFilter" placeholder="Filter category">
                </div>
                <div class="col-md-4">
                    <label for="qbSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="qbSearchFilter" placeholder="Search questions">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Question</th>
                        <th>Type</th>
                        <th>Weight</th>
                        <th>Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="questionBankTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($canManage)
    <div class="modal fade" id="questionBankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionBankModalLabel">Add Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="questionBankForm" class="modal-body">
                    <input type="hidden" id="questionBankEditingId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="qbCategory">Category</label>
                            <input type="text" class="form-control" id="qbCategory" placeholder="e.g. Leadership">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qbType">Type</label>
                            <select class="form-select" id="qbType">
                                <option value="rating">Rating (1–5)</option>
                                <option value="text">Text</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="qbQuestion">Question *</label>
                            <input type="text" class="form-control" id="qbQuestion" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="qbWeight">Default Weight</label>
                            <input type="number" class="form-control" id="qbWeight" min="0" step="0.1" value="1">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="qbActive" checked>
                                <label class="form-check-label" for="qbActive">Active</label>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="questionBankForm" class="btn btn-primary">Save Question</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
