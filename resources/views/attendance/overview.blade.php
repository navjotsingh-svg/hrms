@extends('layouts.app')

@section('title', 'Team Attendance - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Team Attendance</h1>
            <p class="page-subtitle mb-0" id="attendanceOverviewSubtitle">Month matrix for all employees — click any day for details.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="{{ route('web.attendance.index') }}" class="btn btn-outline-secondary btn-sm">Calendar</a>
            @if (Auth::user()->canViewAllAttendance())
                <a href="{{ route('web.attendance.today') }}" class="btn btn-outline-secondary btn-sm">Today</a>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div id="attendanceOverviewAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <div class="attendance-summary-row" id="attendanceOverviewSummaryRow">
                <div class="attendance-summary-item">
                    <span class="fw-semibold" id="attendanceOverviewEmployees">0</span> employees
                </div>
                <div class="attendance-summary-item attendance-summary-item--present">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceOverviewPresent">0</span> present
                </div>
                <div class="attendance-summary-item attendance-summary-item--half-day">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceOverviewHalfDay">0</span> half day
                </div>
                <div class="attendance-summary-item attendance-summary-item--absent">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceOverviewAbsent">0</span> absent
                </div>
                <div class="attendance-summary-item attendance-summary-item--on-leave">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceOverviewOnLeave">0</span> on leave
                </div>
                <div class="attendance-summary-item attendance-summary-item--short-leave">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceOverviewIncomplete">0</span> in progress
                </div>
            </div>
        </div>
    </div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="attendanceOverviewDepartment" class="form-label">Department</label>
                    <select class="form-select" id="attendanceOverviewDepartment">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="attendanceOverviewStatus" class="form-label">Employee status</label>
                    <select class="form-select" id="attendanceOverviewStatus">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="attendanceOverviewSearch" class="form-label">Search</label>
                    <input type="search" class="form-control" id="attendanceOverviewSearch" placeholder="Name or employee code">
                </div>
                <div class="col-md-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="attendanceOverviewReset">Reset filters</button>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-header border-bottom">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <h2 class="content-card-title mb-0" id="attendanceOverviewTitle">Attendance matrix</h2>
                <div class="attendance-period-nav attendance-period-nav--primary" id="attendanceOverviewMonthNav">
                    <button type="button" class="btn btn-outline-secondary btn-sm attendance-period-nav-btn" id="attendanceOverviewPrevMonth" aria-label="Previous month">&larr;</button>
                    <span class="attendance-period-nav-label-text" id="attendanceOverviewMonthLabel">—</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm attendance-period-nav-btn" id="attendanceOverviewNextMonth" aria-label="Next month">&rarr;</button>
                </div>
                <div class="text-muted small" id="attendanceOverviewPaginationInfo">—</div>
            </div>
        </div>
        <div class="companies-table-wrap attendance-matrix-wrap">
            <table class="companies-table attendance-matrix mb-0">
                <thead id="attendanceMatrixHead">
                    <tr>
                        <th colspan="8" class="text-center text-muted py-4">Loading...</th>
                    </tr>
                </thead>
                <tbody id="attendanceMatrixBody"></tbody>
            </table>
        </div>
        <div class="content-card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="attendance-matrix-legend">
                <span class="attendance-matrix-legend-item attendance-matrix-cell--present">P Present</span>
                <span class="attendance-matrix-legend-item attendance-matrix-cell--half-day">HD Half</span>
                <span class="attendance-matrix-legend-item attendance-matrix-cell--absent">A Absent</span>
                <span class="attendance-matrix-legend-item attendance-matrix-cell--on-leave">L Leave</span>
                <span class="attendance-matrix-legend-item attendance-matrix-cell--holiday">H Holiday</span>
                <span class="attendance-matrix-legend-item attendance-matrix-cell--weekly-off">WO Off</span>
                <span class="attendance-matrix-legend-item attendance-matrix-cell--regularization-pending">RP Pending</span>
            </div>
            <ul class="pagination pagination-sm mb-0" id="attendanceOverviewPaginationList"></ul>
        </div>
        <div class="content-card-body border-top pt-2 pb-3">
            <div class="text-muted small text-end" id="attendanceOverviewPaginationSummary"></div>
        </div>
    </div>

    <div class="modal fade" id="attendanceOverviewDayModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="attendanceOverviewDayModalTitle">Day details</h5>
                        <div class="small text-muted" id="attendanceOverviewDayModalSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="attendanceOverviewDayModalBody">
                    <div class="text-center text-muted py-4">Loading...</div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-outline-primary btn-sm d-none" id="attendanceOverviewEmployeeCalendarLink" target="_blank" rel="noopener">Open employee calendar</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/attendance-overview.js'])
@endsection
