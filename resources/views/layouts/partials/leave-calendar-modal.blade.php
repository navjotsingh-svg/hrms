@if (Auth::check() && Auth::user()->canViewAllLeaveRequests())
<div class="modal fade leave-calendar-modal" id="leaveCalendarModal" tabindex="-1" aria-labelledby="leaveCalendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title" id="leaveCalendarModalLabel">Calendar</h5>
                    <div class="small text-muted">Approved employee leaves for the selected month</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div id="topbarLeaveCalendarAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

                <div class="leave-calendar-toolbar mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <button type="button" class="btn btn-link px-0 text-decoration-none" id="topbarLeaveCalendarTodayBtn">View Today</button>
                            <div class="leave-calendar-nav d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm leave-calendar-nav-btn" id="topbarLeaveCalendarPrevBtn" aria-label="Previous month">&lsaquo;</button>
                                <h2 class="h5 mb-0 leave-calendar-month-label" id="topbarLeaveCalendarMonthLabel">—</h2>
                                <button type="button" class="btn btn-outline-secondary btn-sm leave-calendar-nav-btn" id="topbarLeaveCalendarNextBtn" aria-label="Next month">&rsaquo;</button>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="topbarLeaveCalendarShowHolidays" checked>
                                <label class="form-check-label" for="topbarLeaveCalendarShowHolidays">Show holidays</label>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Legends
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-3 leave-calendar-legends-menu" id="topbarLeaveCalendarLegends"></div>
                            </div>
                            <a href="{{ route('web.leave.calendar') }}" class="btn btn-outline-primary btn-sm">Open full page</a>
                        </div>
                    </div>
                    <div class="small text-muted mt-2" id="topbarLeaveCalendarSummary"></div>
                </div>

                <div class="leave-calendar-grid-wrap">
                    <div class="leave-calendar-weekdays">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>
                    <div class="leave-calendar-grid" id="topbarLeaveCalendarGrid">
                        <div class="leave-calendar-loading text-muted">Open calendar to load…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
