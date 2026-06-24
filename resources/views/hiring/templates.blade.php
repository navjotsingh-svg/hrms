@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
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
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small" id="templatesPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="templatesPaginationList"></ul>
            </div>
        </div>
    </div>

    @if ($canManage)
    <div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
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
                        <div class="col-12">
                            <label class="form-label" for="templateBodyHtml">Body (HTML)</label>
                            <textarea class="form-control font-monospace" id="templateBodyHtml" rows="10"></textarea>
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
