@extends('hiring.layout')

@section('hiring-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="requisitionStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="requisitionStatusFilter">
                        <option value="">All</option>
                        <option value="draft">Draft</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="requisitionSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="requisitionSearchFilter" placeholder="Search requisitions">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'requisitionsPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Department</th>
                        <th>Headcount</th>
                        <th>Urgency</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="requisitionsTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        @include('partials.list-pagination-footer', [
            'infoId' => 'requisitionsPaginationInfo',
            'listId' => 'requisitionsPaginationList',
            'perPageId' => 'requisitionsPerPage',
            'wrapClass' => 'content-card-body border-top',
            'ariaLabel' => 'Requisitions pagination',
        ])
    </div>

    @if ($canCreateRequisition)
    <div class="modal fade" id="requisitionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requisitionModalLabel">Create Requisition</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="requisitionForm" class="modal-body">
                    <input type="hidden" id="requisitionEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="requisitionTitle">Title *</label>
                            <input type="text" class="form-control" id="requisitionTitle" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="requisitionDepartment">Department</label>
                            <select class="form-select" id="requisitionDepartment">
                                <option value="">Select department</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="requisitionHeadcount">Headcount</label>
                            <input type="number" class="form-control" id="requisitionHeadcount" min="1" max="100" value="1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="requisitionUrgency">Urgency</label>
                            <select class="form-select" id="requisitionUrgency">
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="requisitionEmploymentType">Employment Type</label>
                            <select class="form-select" id="requisitionEmploymentType">
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="requisitionDescription">Description</label>
                            <textarea class="form-control" id="requisitionDescription" rows="4"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="requisitionForm" class="btn btn-primary">Save Requisition</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
