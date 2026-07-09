@extends('performance.layout')

@section('performance-content')
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="kpiStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="kpiStatusFilter">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="kpiSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="kpiSearchFilter" placeholder="Search KPIs">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-header', ['perPageId' => 'kpiPerPage'])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Employee</th>
                        <th>Target</th>
                        <th>Current</th>
                        <th>Progress</th>
                        <th>Frequency</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="kpiTableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
            @include('partials.list-pagination-footer', [
                'infoId' => 'kpiPaginationInfo',
                'listId' => 'kpiPaginationList',
                'perPageId' => 'kpiPerPage',
                'wrapClass' => 'content-card-body border-top',
                'ariaLabel' => 'KPI pagination',
            ])
    </div>

    @if ($canManage)
    <div class="modal fade" id="kpiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="kpiModalLabel">Create KPI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="kpiForm" class="modal-body">
                    <input type="hidden" id="kpiEditingId">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="kpiTitle">Title *</label>
                            <input type="text" class="form-control" id="kpiTitle" required>
                        </div>
                        <div class="col-md-4">
                            @include('partials.employee-search-select', [
                                'inputId' => 'kpiEmployeeSearch',
                                'hiddenId' => 'kpiEmployeeId',
                                'label' => 'Employee *',
                                'placeholder' => 'Search employee',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="kpiDescription">Description</label>
                            <textarea class="form-control" id="kpiDescription" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="kpiTarget">Target</label>
                            <input type="number" class="form-control" id="kpiTarget" min="0" step="0.01" value="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="kpiCurrent">Current</label>
                            <input type="number" class="form-control" id="kpiCurrent" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="kpiUnit">Unit</label>
                            <input type="text" class="form-control" id="kpiUnit" placeholder="%, ₹, units">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="kpiFrequency">Frequency</label>
                            <select class="form-select" id="kpiFrequency">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly" selected>Quarterly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="kpiPeriodStart">Period Start</label>
                            <input type="date" class="form-control" id="kpiPeriodStart">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="kpiPeriodEnd">Period End</label>
                            <input type="date" class="form-control" id="kpiPeriodEnd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="kpiFormStatus">Status</label>
                            <select class="form-select" id="kpiFormStatus">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="kpiForm" class="btn btn-primary">Save KPI</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
