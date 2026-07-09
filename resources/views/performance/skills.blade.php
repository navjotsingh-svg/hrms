@extends('performance.layout')

@section('performance-content')
    <ul class="nav nav-tabs mb-3" id="skillsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="profiles-tab" data-bs-toggle="tab" data-bs-target="#profilesPanel" type="button">Employee Skill Profiles</button>
        </li>
        @if ($canManage)
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="library-tab" data-bs-toggle="tab" data-bs-target="#libraryPanel" type="button">Competency Library</button>
        </li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="profilesPanel">
            <div class="content-card companies-list-card">
                <div class="content-card-body companies-filter-bar border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="skillProfileSearchFilter" class="form-label">Search</label>
                            <input type="search" class="form-control" id="skillProfileSearchFilter" placeholder="Search employees or competencies">
                        </div>
                    </div>
                </div>
                @include('partials.list-pagination-header', ['perPageId' => 'skillProfilesPerPage'])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Competency</th>
                                <th>Current Level</th>
                                <th>Target Level</th>
                                <th>Gap</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="skillProfilesTableBody">
                            <tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'skillProfilesPaginationInfo',
                    'listId' => 'skillProfilesPaginationList',
                    'perPageId' => 'skillProfilesPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Skill profiles pagination',
                ])
            </div>
        </div>

        @if ($canManage)
        <div class="tab-pane fade" id="libraryPanel">
            <div class="content-card companies-list-card">
                <div class="content-card-body companies-filter-bar border-bottom">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="competencySearchFilter" class="form-label">Search</label>
                            <input type="search" class="form-control" id="competencySearchFilter" placeholder="Search competencies">
                        </div>
                    </div>
                </div>
                @include('partials.list-pagination-header', ['perPageId' => 'competenciesPerPage'])
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Max Level</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="competenciesTableBody">
                            <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                @include('partials.list-pagination-footer', [
                    'infoId' => 'competenciesPaginationInfo',
                    'listId' => 'competenciesPaginationList',
                    'perPageId' => 'competenciesPerPage',
                    'wrapClass' => 'content-card-body border-top',
                    'ariaLabel' => 'Competencies pagination',
                ])
            </div>
        </div>
        @endif
    </div>

    <div class="modal fade" id="skillProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="skillProfileModalLabel">Assign Competency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="skillProfileForm" class="modal-body">
                    <input type="hidden" id="skillProfileEditingId">
                    <div class="mb-3">
                        @include('partials.employee-search-select', [
                            'inputId' => 'skillProfileEmployeeSearch',
                            'hiddenId' => 'skillProfileEmployeeId',
                            'label' => 'Employee *',
                            'placeholder' => 'Search employee',
                            'required' => true,
                        ])
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="skillProfileCompetencyId">Competency *</label>
                        <select class="form-select" id="skillProfileCompetencyId" required>
                            <option value="">Select competency</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="skillProfileCurrentLevel">Current Level</label>
                            <input type="number" class="form-control" id="skillProfileCurrentLevel" min="1" max="10" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="skillProfileTargetLevel">Target Level</label>
                            <input type="number" class="form-control" id="skillProfileTargetLevel" min="1" max="10" value="3">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="skillProfileNotes">Notes</label>
                        <textarea class="form-control" id="skillProfileNotes" rows="2"></textarea>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="skillProfileForm" class="btn btn-primary">Save Profile</button>
                </div>
            </div>
        </div>
    </div>

    @if ($canManage)
    <div class="modal fade" id="competencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="competencyModalLabel">Create Competency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="competencyForm" class="modal-body">
                    <input type="hidden" id="competencyEditingId">
                    <div class="mb-3">
                        <label class="form-label" for="competencyName">Name *</label>
                        <input type="text" class="form-control" id="competencyName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="competencyCategory">Category</label>
                        <input type="text" class="form-control" id="competencyCategory">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="competencyDescription">Description</label>
                        <textarea class="form-control" id="competencyDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="competencyMaxLevel">Max Level</label>
                        <input type="number" class="form-control" id="competencyMaxLevel" min="1" max="10" value="5">
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="competencyForm" class="btn btn-primary">Save Competency</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
