@extends('layouts.app')

@section('title', "Today's Attendance - " . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Today's Attendance</h1>
            <p class="page-subtitle mb-0" id="attendanceTodaySubtitle">See who has marked attendance, who is present, absent, or on leave.</p>
        </div>
    </div>
@endsection

@section('content')
    <div id="attendanceTodayAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card mb-4">
        <div class="content-card-body">
            <div class="attendance-period-nav attendance-period-nav--primary attendance-period-nav--date mb-3">
                <div class="attendance-period-nav-controls">
                    <button type="button" class="btn btn-outline-secondary attendance-period-nav-btn" id="attendanceTodayPrevDay" aria-label="Previous day">&larr;</button>
                    <input type="date" class="form-control attendance-period-date-input" id="attendanceTodayDate" aria-label="Attendance date">
                    <button type="button" class="btn btn-outline-secondary attendance-period-nav-btn" id="attendanceTodayNextDay" aria-label="Next day">&rarr;</button>
                </div>
                <div class="attendance-period-nav-actions">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="attendanceTodayGoToday">Today</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="attendanceTodayRefresh">Refresh</button>
                </div>
            </div>
            <div class="attendance-summary-row" id="attendanceTodaySummaryRow">
                <div class="attendance-summary-item">
                    <span class="fw-semibold" id="attendanceTodayTotal">0</span> employees
                </div>
                <div class="attendance-summary-item attendance-summary-item--present">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceTodayPresent">0</span> present
                </div>
                <div class="attendance-summary-item attendance-summary-item--half-day">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceTodayHalfDay">0</span> half day
                </div>
                <div class="attendance-summary-item attendance-summary-item--absent">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceTodayAbsent">0</span> absent
                </div>
                <div class="attendance-summary-item attendance-summary-item--on-leave">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceTodayOnLeave">0</span> on leave
                </div>
                <div class="attendance-summary-item attendance-summary-item--short-leave">
                    <span class="attendance-summary-dot"></span>
                    <span id="attendanceTodayIncomplete">0</span> in progress
                </div>
                <div class="attendance-summary-item ms-auto text-muted">
                    Marked: <span id="attendanceTodayMarked">0</span> · Not marked: <span id="attendanceTodayNotMarked">0</span>
                </div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-body border-bottom">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    @include('partials.employee-search-select', [
                        'inputId' => 'attendanceTodayEmployeeInput',
                        'hiddenId' => 'attendanceTodayEmployeeId',
                    ])
                </div>
                <div class="col-md-2">
                    <label for="attendanceTodayDepartment" class="form-label">Department</label>
                    <select class="form-select" id="attendanceTodayDepartment">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="attendanceTodayStatus" class="form-label">Status</label>
                    <select class="form-select" id="attendanceTodayStatus">
                        <option value="">All statuses</option>
                        <option value="present">Present</option>
                        <option value="half_day">Half Day</option>
                        <option value="absent">Absent</option>
                        <option value="on_leave">On Leave</option>
                        <option value="incomplete">In Progress</option>
                        <option value="regularization_pending">Regularization Pending</option>
                        <option value="holiday">Holiday</option>
                        <option value="weekly_off">Weekly Off</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="attendanceTodayMarkedFilter" class="form-label">Marked</label>
                    <select class="form-select" id="attendanceTodayMarkedFilter">
                        <option value="">All</option>
                        <option value="yes">Marked</option>
                        <option value="no">Not marked</option>
                        <option value="partial">Partial</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" id="attendanceTodayReset">Reset</button>
                </div>
            </div>
        </div>
        <div class="content-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3rem;">#</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Worked</th>
                            <th>Marked</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTodayTableBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Loading today's attendance...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @vite(['resources/js/attendance-today.js'])
@endsection
