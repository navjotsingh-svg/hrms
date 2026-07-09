@extends('performance.layout')

@section('performance-content')
    <ul class="nav nav-tabs mb-3" id="compensationTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="bands-tab" data-bs-toggle="tab" data-bs-target="#bandsPanel" type="button">Salary Bands</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="merit-tab" data-bs-toggle="tab" data-bs-target="#meritPanel" type="button">Merit Recommendations</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="bandsPanel">
            <div class="content-card companies-list-card">
                <div class="content-card-body companies-filter-bar border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="bandSearchFilter" class="form-label">Search</label>
                            <input type="search" class="form-control" id="bandSearchFilter" placeholder="Search salary bands">
                        </div>
                    </div>
                </div>
                @include('partials.list-pagination-header', ['perPageId' => 'bandsPerPage'])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Band</th>
                                <th>Grade</th>
                                <th>Min</th>
                                <th>Mid</th>
                                <th>Max</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bandsTableBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'bandsPaginationInfo',
                    'listId' => 'bandsPaginationList',
                    'perPageId' => 'bandsPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Salary bands pagination',
                ])
            </div>
        </div>

        <div class="tab-pane fade" id="meritPanel">
            <div class="content-card companies-list-card">
                <div class="content-card-body companies-filter-bar border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="meritStatusFilter" class="form-label">Status</label>
                            <select class="form-select" id="meritStatusFilter">
                                <option value="">All</option>
                                <option value="draft">Draft</option>
                                <option value="proposed">Proposed</option>
                                <option value="approved">Approved</option>
                                <option value="applied">Applied</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="meritSearchFilter" class="form-label">Search</label>
                            <input type="search" class="form-control" id="meritSearchFilter" placeholder="Search employees">
                        </div>
                    </div>
                </div>
                @include('partials.list-pagination-header', ['perPageId' => 'meritPerPage'])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Current Salary</th>
                                <th>Increase %</th>
                                <th>New Salary</th>
                                <th>Band</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="meritTableBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'meritPaginationInfo',
                    'listId' => 'meritPaginationList',
                    'perPageId' => 'meritPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Merit recommendations pagination',
                ])
            </div>
        </div>
    </div>

    <div class="modal fade" id="bandModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bandModalLabel">Create Salary Band</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="bandForm" class="modal-body">
                    <input type="hidden" id="bandEditingId">
                    <div class="mb-3">
                        <label class="form-label" for="bandName">Band Name *</label>
                        <input type="text" class="form-control" id="bandName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bandGrade">Grade</label>
                        <input type="text" class="form-control" id="bandGrade">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="bandMin">Min Salary *</label>
                            <input type="number" class="form-control" id="bandMin" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bandMid">Mid Salary</label>
                            <input type="number" class="form-control" id="bandMid" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="bandMax">Max Salary *</label>
                            <input type="number" class="form-control" id="bandMax" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="bandDescription">Description</label>
                        <textarea class="form-control" id="bandDescription" rows="2"></textarea>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="bandForm" class="btn btn-primary">Save Band</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="meritModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="meritModalLabel">Create Merit Recommendation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="meritForm" class="modal-body">
                    <input type="hidden" id="meritEditingId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            @include('partials.employee-search-select', [
                                'inputId' => 'meritEmployeeSearch',
                                'hiddenId' => 'meritEmployeeId',
                                'label' => 'Employee *',
                                'placeholder' => 'Search employee',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="meritBandId">Salary Band</label>
                            <select class="form-select" id="meritBandId">
                                <option value="">Select band</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="meritCurrentSalary">Current Salary</label>
                            <input type="number" class="form-control" id="meritCurrentSalary" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="meritIncreasePercent">Increase %</label>
                            <input type="number" class="form-control" id="meritIncreasePercent" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="meritNewSalary">New Salary</label>
                            <input type="number" class="form-control" id="meritNewSalary" min="0" step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="meritNotes">Notes</label>
                            <textarea class="form-control" id="meritNotes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="meritForm" class="btn btn-primary">Save Recommendation</button>
                </div>
            </div>
        </div>
    </div>
@endsection
