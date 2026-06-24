@extends('layouts.app')

@section('title', 'Projects - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Projects</h1>
            <p class="page-subtitle mb-0">Create projects and assign team members for timesheet tracking.</p>
        </div>
        <button type="button" class="btn btn-primary" id="openProjectModalBtn">
            + Add Project
        </button>
    </div>
@endsection

@section('content')
    <div id="projectsAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card companies-list-card">
        <div class="content-card-body companies-filter-bar border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="filterSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="filterSearch" placeholder="Project name or description...">
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th class="companies-th-serial">#</th>
                        <th>Project</th>
                        <th>Timeline</th>
                        <th>Assignees</th>
                        <th>Status</th>
                        <th class="companies-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="projectsTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">Loading projects...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="content-card-body border-top companies-pagination-footer" id="projectsPagination">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="projectsPaginationInfo">Loading pagination...</div>
                <nav aria-label="Projects pagination">
                    <ul class="pagination pagination-sm mb-0" id="projectsPaginationList"></ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="projectForm" novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="projectModalLabel">Add Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="projectFormAlert" class="alert alert-danger d-none" role="alert"></div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="projectName" class="form-label">Project name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="projectName" required maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label for="projectStatus" class="form-label">Status</label>
                                <select class="form-select" id="projectStatus">
                                    <option value="active">Active</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="projectStartDate" class="form-label">Start date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="projectStartDate" required>
                            </div>
                            <div class="col-md-6">
                                <label for="projectEndDate" class="form-label">End date</label>
                                <input type="date" class="form-control" id="projectEndDate">
                            </div>
                            <div class="col-12">
                                <label for="projectDescription" class="form-label">Description</label>
                                <textarea class="form-control" id="projectDescription" rows="3" maxlength="5000"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Assignees <span class="text-danger">*</span></label>
                                @include('partials.employee-search-select', [
                                    'inputId' => 'projectAssigneeInput',
                                    'hiddenId' => 'projectAssigneeHidden',
                                    'label' => 'Add assignee',
                                    'placeholder' => 'Search by employee name...',
                                ])
                                <div class="d-flex flex-wrap gap-2 mt-2" id="projectAssigneeChips"></div>
                                <div class="form-text" id="projectAssigneeHelp">Search and add employees by name.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="projectFormSubmit">Save Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite(['resources/js/projects-index.js'])
@endsection
