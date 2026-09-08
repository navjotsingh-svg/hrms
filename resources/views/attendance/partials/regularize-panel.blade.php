<div class="offcanvas offcanvas-end attendance-regularize-panel" tabindex="-1" id="attendanceRegularizePanel" aria-labelledby="attendanceRegularizePanelLabel">
    <form id="regularizeForm" class="attendance-regularize-panel-form">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title mb-1" id="attendanceRegularizePanelLabel">Attendance Request</h5>
                <div class="regularize-modal-timezone small text-muted" id="regularizePanelTimezone">—</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        <ul class="nav nav-tabs attendance-regularize-panel-tabs px-3 pt-2" id="regularizePanelTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button
                    type="button"
                    class="nav-link active"
                    id="regularizeTabNewBtn"
                    data-regularize-panel-tab="new"
                    role="tab"
                    aria-selected="true"
                    aria-controls="regularizeTabNewPanel"
                >
                    New Request
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button
                    type="button"
                    class="nav-link"
                    id="regularizeTabSubmittedBtn"
                    data-regularize-panel-tab="submitted"
                    role="tab"
                    aria-selected="false"
                    aria-controls="regularizeTabSubmittedPanel"
                >
                    Submitted
                    <span class="badge rounded-pill text-bg-warning ms-1 d-none" id="regularizeSubmittedBadge">0</span>
                </button>
            </li>
        </ul>

        <div class="offcanvas-body attendance-regularize-panel-body">
            <input type="hidden" id="regularize_employee_id" name="employee_id">

            <div id="regularizeTabNewPanel" data-regularize-panel-panel="new" role="tabpanel">
                <div id="regularizePolicyBanner" class="alert alert-info d-none mb-3 py-2 small" role="status"></div>
                <p class="small text-muted mb-2">Want to regularize for a different date?</p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addRegularizeDateBtn">+ New Date</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addRegularizeRangeBtn">+ Date Range</button>
                </div>
                <div class="regularize-dates-panel mb-3">
                    <div class="regularize-dates-panel-header">
                        <span class="fw-semibold">Workday Date</span>
                    </div>
                    <ul class="regularize-dates-list list-unstyled mb-0" id="regularizeSelectedDatesList">
                        <li class="regularize-dates-empty text-muted small py-3 px-3">Add at least one date to continue.</li>
                    </ul>
                </div>
                <div class="mb-0">
                    <label for="regularizeReason" class="form-label">Reason (shared for all selected days)</label>
                    <textarea class="form-control" id="regularizeReason" name="reason" rows="3" minlength="10" required placeholder="Explain why attendance was missed or needs correction for the selected days"></textarea>
                </div>
            </div>

            <div id="regularizeTabSubmittedPanel" class="d-none" data-regularize-panel-panel="submitted" role="tabpanel">
                <p class="small text-muted mb-3">Regularization requests you have submitted that are awaiting approval.</p>
                <div id="regularizeSubmittedList">
                    <div class="text-muted small py-3">Loading submitted requests...</div>
                </div>
            </div>
        </div>

        <div class="offcanvas-footer attendance-regularize-panel-footer border-top p-3" id="regularizePanelFooter">
            <button type="submit" class="btn btn-primary w-100" id="regularizeSubmitBtn" disabled>Submit for 0 day(s)</button>
        </div>
    </form>
</div>

<div class="modal fade" id="pickRegularizeDateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="pickRegularizeDateSelect" class="form-label">Select a workday</label>
                <select class="form-select" id="pickRegularizeDateSelect"></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmPickRegularizeDateBtn">Add Date</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pickRegularizeRangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Date Range</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="regularizeRangeFrom" class="form-label">From</label>
                    <input type="date" class="form-control" id="regularizeRangeFrom">
                </div>
                <div class="mb-0">
                    <label for="regularizeRangeTo" class="form-label">To</label>
                    <input type="date" class="form-control" id="regularizeRangeTo">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmRegularizeRangeBtn">Add Dates</button>
            </div>
        </div>
    </div>
</div>
