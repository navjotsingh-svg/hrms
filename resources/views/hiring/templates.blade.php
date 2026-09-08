@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        @include('partials.list-pagination-top', [
    'infoId' => 'templatesPaginationInfo',
    'listId' => 'templatesPaginationList',
    'perPageId' => 'templatesPerPage',
    'ariaLabel' => 'Templates pagination',
])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Default</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="templatesTableBody">
                    <tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'templatesPaginationInfo',
            'listId' => 'templatesPaginationList',
            'perPageId' => 'templatesPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Templates pagination',
        ])
    </div>

    @if ($canManage)
    <div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalLabel">Create Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="templateForm" class="modal-body">
                    <input type="hidden" id="templateEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="templateName">Name *</label>
                            <input type="text" class="form-control" id="templateName" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="templateType">Type</label>
                            <select class="form-select" id="templateType">
                                <option value="offer">Offer Letter</option>
                                <option value="email">Email</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <span class="small text-muted">Quick start:</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="useOfferSampleBtn">Use Offer Letter Sample</button>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Template body</label>
                            <div id="templateBodyEditor" class="doc-letter-editor"></div>
                            <textarea class="d-none" id="templateBodyHtml" aria-hidden="true"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="hiring-template-fields-card border rounded p-3 bg-light">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold small">Dynamic fields</div>
                                        <div class="text-muted small">Click a field to insert it at the cursor in the template body.</div>
                                    </div>
                                </div>
                                <div id="templatePlaceholderGroups"></div>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="templateForm" class="btn btn-primary">Save Template</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
