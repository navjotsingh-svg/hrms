@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="offerStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="offerStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="accepted">Accepted</option>
                        <option value="declined">Declined</option>
                        <option value="withdrawn">Withdrawn</option>
                    </select>
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'offersPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Candidate</th>
                        <th>Job</th>
                        <th>CTC</th>
                        <th>Joining Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="offersTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'offersPaginationInfo',
            'listId' => 'offersPaginationList',
            'perPageId' => 'offersPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Offers pagination',
        ])
    </div>

    @if ($canManage)
    <div class="modal fade" id="offerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="offerModalLabel">Create Offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="offerForm" class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="offerCandidate">Candidate *</label>
                            <select class="form-select" id="offerCandidate" required>
                                <option value="">Select candidate</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="offerJob">Job</label>
                            <select class="form-select" id="offerJob">
                                <option value="">Select job</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="offerTitle">Title *</label>
                            <input type="text" class="form-control" id="offerTitle" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="offerTemplate">Template</label>
                            <select class="form-select" id="offerTemplate">
                                <option value="">Select template</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="offerCtc">Offered CTC</label>
                            <input type="number" class="form-control" id="offerCtc" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="offerJoiningDate">Joining Date</label>
                            <input type="date" class="form-control" id="offerJoiningDate">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="offerLetterHtml">Letter (HTML)</label>
                            <textarea class="form-control font-monospace" id="offerLetterHtml" rows="6"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="offerForm" class="btn btn-primary">Create Offer</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
