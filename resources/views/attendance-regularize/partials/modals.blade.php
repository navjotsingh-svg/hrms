<div class="modal fade" id="regularizeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content regularize-request-modal">
            <form id="regularizeForm">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title mb-1" id="regularizeModalTitle">Attendance Request</h5>
                        <div class="regularize-modal-timezone small text-muted" id="regularizeModalTimezone">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <input type="hidden" id="regularize_employee_id" name="employee_id">
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
                    <div class="row g-3 mb-3" id="regularizeOriginalTimesWrap">
                        <div class="col-12">
                            <div class="small text-muted mb-1">Current login / logout on record</div>
                            <div class="fw-semibold" id="regularizeOriginalTimes">—</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="punch_in_time" class="form-label">New login (punch in)</label>
                            <input type="time" class="form-control" id="punch_in_time" name="punch_in_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="punch_out_time" class="form-label">New logout (punch out)</label>
                            <input type="time" class="form-control" id="punch_out_time" name="punch_out_time">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" minlength="10" required placeholder="Explain why attendance was missed or needs correction"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary w-100 w-sm-auto" id="regularizeSubmitBtn" disabled>Submit for 0 day(s)</button>
                </div>
            </form>
        </div>
    </div>
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
