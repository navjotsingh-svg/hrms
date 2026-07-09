@extends('layouts.app')

@section('title', 'Bulk Import Employees - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Bulk Import Employees</h1>
            <p class="page-subtitle mb-0">Upload a spreadsheet, map columns, and import many employees at once.</p>
        </div>
        <a href="{{ route('web.employees.index') }}" class="btn btn-outline-secondary">Back to employees</a>
    </div>
@endsection

@section('content')
    <div id="employeeBulkImportAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card bulk-import-page">
        <div class="content-card-body border-bottom">
            <ol class="bulk-import-steps mb-0" id="bulkImportSteps">
                <li class="bulk-import-step bulk-import-step--active" data-step="upload">
                    <span class="bulk-import-step-number">1</span>
                    <span class="bulk-import-step-label">Upload file</span>
                </li>
                <li class="bulk-import-step" data-step="mapping">
                    <span class="bulk-import-step-number">2</span>
                    <span class="bulk-import-step-label">Map columns</span>
                </li>
                <li class="bulk-import-step" data-step="result">
                    <span class="bulk-import-step-number">3</span>
                    <span class="bulk-import-step-label">Review results</span>
                </li>
            </ol>
        </div>

        <div class="content-card-body bulk-import-body">
            <div id="bulkImportStepUpload">
                <p class="text-muted mb-4">Upload any Excel or CSV file. You do not need a fixed template — after upload, map each file column to a system field or store it as extra data.</p>
                <div class="bulk-import-upload-panel border rounded p-4 bg-light">
                    <label for="bulkImportFileInput" class="form-label fw-semibold">Choose file</label>
                    <input type="file" class="form-control mb-3" id="bulkImportFileInput" accept=".xlsx,.xls,.csv,.txt">
                    <p class="small text-muted mb-0">Supported formats: .xlsx, .xls, .csv (max 10 MB)</p>
                </div>
            </div>

            <div id="bulkImportStepMapping" class="d-none">
                <p class="text-muted mb-3">Link each file column to an employee field. Unmapped columns can be stored separately and linked to the imported row.</p>
                <div class="bulk-import-scroll-panel table-responsive mb-4">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 35%;">File column</th>
                                <th>Map to system field</th>
                            </tr>
                        </thead>
                        <tbody id="bulkImportMappingBody"></tbody>
                    </table>
                </div>
                <h6 class="mb-2">Preview</h6>
                <div class="bulk-import-scroll-panel table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light sticky-top" id="bulkImportPreviewHead"></thead>
                        <tbody id="bulkImportPreviewBody"></tbody>
                    </table>
                </div>
            </div>

            <div id="bulkImportStepResult" class="d-none">
                <div id="bulkImportResultSummary"></div>
                <div id="bulkImportResultFailed" class="d-none"></div>
                <div class="mt-3 d-none" id="bulkImportAiExplainWrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="bulkImportAiExplainBtn">AI explain errors</button>
                    <div id="bulkImportAiExplainResult" class="alert alert-info mt-3 d-none"></div>
                </div>
            </div>
        </div>

        <div class="content-card-body border-top bulk-import-actions">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <button type="button" class="btn btn-outline-secondary d-none" id="bulkImportBackBtn">Back</button>
                <div class="d-flex flex-wrap gap-2 ms-auto">
                    <a href="{{ route('web.employees.index') }}" class="btn btn-outline-secondary" id="bulkImportCancelBtn">Cancel</a>
                    <button type="button" class="btn btn-primary" id="bulkImportUploadBtn">Upload & Map Columns</button>
                    <button type="button" class="btn btn-primary d-none" id="bulkImportConfirmBtn">Confirm & Import</button>
                    <a href="{{ route('web.employees.index') }}" class="btn btn-primary d-none" id="bulkImportDoneBtn">View employees</a>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/employee-bulk-import.js'])
@endsection
