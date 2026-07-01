@extends('layouts.app')

@section('title', 'Offboarding - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Offboarding</h1>
            <p class="page-subtitle mb-0">Resignation requests, clearance, asset return, and F&F settlements.</p>
        </div>
        <div class="d-flex gap-2">
            @if (Auth::user()->canApplyOffboarding())
                <a href="{{ route('web.offboarding.apply') }}" class="btn btn-primary">Submit Resignation</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="offboardingIndexRoot" data-can-manage="{{ Auth::user()->canManageOffboarding() ? '1' : '0' }}">
    <div id="offboardingIndexAlert" class="alert alert-success alert-dismissible fade show d-none"></div>

    @if (Auth::user()->canManageOffboarding())
        <div class="content-card mb-4">
            <div class="content-card-header border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="content-card-title mb-0">Exit Survey Questions</h2>
                    <p class="text-muted small mb-0">Customize the questions employees answer during offboarding.</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="exitSurveyReseedBtn">Reset Defaults</button>
                    <button type="button" class="btn btn-primary btn-sm" id="exitSurveyCreateBtn">Add Question</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="companies-table table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="exitSurveyQuestionsBody">
                        <tr><td colspan="6" class="text-center text-muted py-5">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (Auth::user()->canApproveOffboarding() || Auth::user()->canManageOffboarding())
        <div class="content-card mb-4" id="resignationPendingCard">
            <div class="content-card-header border-bottom d-flex align-items-center justify-content-between">
                <h2 class="content-card-title mb-0">Pending Resignations</h2>
                <span class="badge bg-warning text-dark d-none" id="resignationPendingBadge">0</span>
            </div>
            <div class="content-card-body" id="resignationPendingContainer">
                <div class="text-muted">Loading pending resignations...</div>
            </div>
        </div>
    @endif

    <div class="content-card mb-4">
        <div class="content-card-header border-bottom">
            <h2 class="content-card-title mb-0">Exit Cases</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Last Working Date</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="exitCasesTableBody">
                    <tr><td colspan="6" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="content-card-body border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="small text-muted" id="exitCasesPaginationInfo"></div>
                <ul class="pagination pagination-sm mb-0" id="exitCasesPaginationList"></ul>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header border-bottom">
            <h2 class="content-card-title mb-0">Resignation Requests</h2>
        </div>
        <div class="table-responsive">
            <table class="companies-table table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Proposed LWD</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="resignationRequestsTableBody">
                    <tr><td colspan="5" class="text-center text-muted py-5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    </div>

    @if (Auth::user()->canManageOffboarding())
        <div class="modal fade" id="exitSurveyQuestionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="exitSurveyQuestionForm">
                        <input type="hidden" id="exitSurveyQuestionId">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exitSurveyQuestionModalTitle">Exit Survey Question</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="exitSurveyQuestionText" class="form-label">Question</label>
                                    <textarea class="form-control" id="exitSurveyQuestionText" rows="2" required maxlength="2000"></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label for="exitSurveyQuestionType" class="form-label">Type</label>
                                    <select class="form-select" id="exitSurveyQuestionType" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label for="exitSurveyQuestionSort" class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" id="exitSurveyQuestionSort" min="0" max="999">
                                </div>
                                <div class="col-md-4">
                                    <label for="exitSurveyQuestionStatus" class="form-label">Status</label>
                                    <select class="form-select" id="exitSurveyQuestionStatus">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-12 d-none" id="exitSurveyOptionsWrap">
                                    <label for="exitSurveyQuestionOptions" class="form-label">Options (one per line)</label>
                                    <textarea class="form-control" id="exitSurveyQuestionOptions" rows="4" placeholder="Better compensation&#10;Career growth&#10;Work-life balance"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="exitSurveyQuestionRequired" checked>
                                        <label class="form-check-label" for="exitSurveyQuestionRequired">Required question</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Question</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @vite(['resources/js/offboarding-index.js'])
@endsection
