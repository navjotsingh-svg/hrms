@extends('performance.layout')

@section('performance-content')
    <div class="alert alert-light border mb-3">
        <strong>Goals → Tasks → KPIs flow:</strong>
        Break each goal into <em>tasks/key results</em>. Link a task to a KPI to sync progress automatically.
        Goal achievement % is the weighted average of task progress; parent goals roll up from child goals when cascaded.
        Create and update KPIs on the <a href="{{ route('web.performance.kpi') }}" class="alert-link">KPI page</a> — linked goal tasks and overall goal % update dynamically.
    </div>
    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="goalLevelFilter" class="form-label">Level</label>
                    <select class="form-select" id="goalLevelFilter">
                        <option value="">All levels</option>
                        <option value="company">Company</option>
                        <option value="department">Department</option>
                        <option value="individual">Individual</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="goalStatusFilter" class="form-label">Status</label>
                    <select class="form-select" id="goalStatusFilter">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="goalSearchFilter" class="form-label">Search</label>
                    <input type="search" class="form-control" id="goalSearchFilter" placeholder="Search goals, employees, or departments">
                </div>
            </div>
        </div>
        @include('partials.list-pagination-top', [
    'infoId' => 'goalsPaginationInfo',
    'listId' => 'goalsPaginationList',
    'perPageId' => 'goalsPerPage',
    'ariaLabel' => 'Goals pagination',
])
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Level</th>
                        <th>Owner</th>
                        <th>Period</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="goalsTableBody">
                    <tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
            @include('partials.list-pagination-footer', [
                'infoId' => 'goalsPaginationInfo',
                'listId' => 'goalsPaginationList',
                'perPageId' => 'goalsPerPage',
                'wrapClass' => 'content-card-body border-top',
                'ariaLabel' => 'Goals pagination',
            ])
    </div>

    <div class="modal fade" id="goalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="goalModalLabel">Create Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="goalForm" class="modal-body">
                    <input type="hidden" id="goalEditingId">
                    <div class="row g-3">
                        <div class="col-md-4" id="goalLevelFieldWrap">
                            <label class="form-label" for="goalLevel">Goal Level *</label>
                            <select class="form-select" id="goalLevel">
                                <option value="individual">Individual</option>
                                <option value="department">Department</option>
                                <option value="company">Company</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="goalTitle">Title *</label>
                            <input type="text" class="form-control" id="goalTitle" required>
                        </div>
                        <div class="col-md-6 d-none" id="goalDepartmentWrap">
                            <label class="form-label" for="goalDepartmentId">Department *</label>
                            <select class="form-select" id="goalDepartmentId">
                                <option value="">Select department</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="goalEmployeeWrap">
                            @include('partials.employee-search-select', [
                                'inputId' => 'goalEmployeeSearch',
                                'hiddenId' => 'goalEmployeeId',
                                'label' => 'Employee *',
                                'placeholder' => 'Search employee by name or code…',
                                'required' => true,
                            ])
                        </div>
                        <div class="col-md-6 d-none" id="goalSelfEmployeeWrap">
                            <label class="form-label">Employee</label>
                            <p class="form-control-plaintext text-muted mb-0" id="goalSelfEmployeeLabel">This goal will be assigned to you.</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalStatus">Status</label>
                            <select class="form-select" id="goalStatus">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="goalDescription">Description</label>
                            <textarea class="form-control" id="goalDescription" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalPeriodStart">Period Start</label>
                            <input type="date" class="form-control" id="goalPeriodStart">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalPeriodEnd">Period End</label>
                            <input type="date" class="form-control" id="goalPeriodEnd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="goalVisibility">Visibility</label>
                            <select class="form-select" id="goalVisibility">
                                <option value="team">Team</option>
                                <option value="private">Private</option>
                                <option value="company">Company</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Tasks &amp; Key Results</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="addKeyResultBtn">+ Add Task</button>
                            </div>
                            <p class="form-text mb-2">Add measurable tasks. Optionally link each task to a KPI — progress syncs from the KPI automatically.</p>
                            <div id="keyResultsList" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="goalForm" class="btn btn-primary">Save Goal</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="goalTrackingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="goalTrackingModalLabel">Goal Progress</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Overall achievement</span>
                            <span class="fw-semibold" id="goalTrackingOverallPct">0%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" id="goalTrackingOverallBar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div id="goalTrackingMeta" class="small text-muted mb-3"></div>
                    <h6 class="mb-2">Tasks &amp; Key Results</h6>
                    <div id="goalTrackingTasks" class="d-flex flex-column gap-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection
