<div class="profile-tab-section">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h4 class="profile-info-card-title mb-1">Portal Journey</h4>
            <p class="text-muted small mb-0">Complete history of joining, requests, approvals, profile submissions, attendance, and portal activity.</p>
            <p class="text-muted small mb-0 mt-1" id="employeeJourneyRangeSummary"></p>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="employee-journey-range-filter">
                <label for="employeeJourneyRangePreset" class="form-label small mb-1">Period</label>
                <select class="form-select form-select-sm" id="employeeJourneyRangePreset">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month" selected>This Month</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div id="employeeJourneyCustomRange" class="d-none d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label for="employeeJourneyFromDate" class="form-label small mb-1">From</label>
                    <input type="date" class="form-control form-control-sm" id="employeeJourneyFromDate">
                </div>
                <div>
                    <label for="employeeJourneyToDate" class="form-label small mb-1">To</label>
                    <input type="date" class="form-control form-control-sm" id="employeeJourneyToDate">
                </div>
                <button type="button" class="btn btn-primary btn-sm" id="employeeJourneyApplyRangeBtn">Apply</button>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="employeeJourneyRefreshBtn">Refresh</button>
        </div>
    </div>

    @include('partials.list-pagination-header', [
        'perPageId' => 'employeeJourneyPerPage',
        'wrapClass' => 'mb-3 companies-pagination-header',
    ])
    <div id="employeeJourneyList" class="activity-timeline employee-journey-timeline">
        <div class="text-muted py-4 text-center">Open this tab to load portal journey.</div>
    </div>

    @include('partials.list-pagination-footer', [
        'infoId' => 'employeeJourneyPaginationInfo',
        'listId' => 'employeeJourneyPaginationList',
        'perPageId' => 'employeeJourneyPerPage',
        'wrapId' => 'employeeJourneyPagination',
        'wrapClass' => 'mt-3 companies-pagination-footer',
        'ariaLabel' => 'Portal journey pagination',
    ])
</div>
