@extends('layouts.app')

@section('title', 'Attendance - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Attendance</h1>
            <p class="page-subtitle mb-0" id="attendanceSubtitle">View attendance calendar and daily work hours.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if (Auth::user()->canViewAllAttendance() || Auth::user()->canViewTeamAttendance())
                <a href="{{ route('web.attendance.overview') }}" class="btn btn-outline-primary btn-sm">Team view</a>
            @endif
            @if (Auth::user()->canRegularizeAttendance())
                <a href="{{ route('web.attendance.regularize.index') }}" class="btn btn-outline-primary btn-sm">Regularize</a>
            @endif
            <div class="d-flex align-items-center gap-2" id="attendanceMonthNav">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="attendancePrevMonth" aria-label="Previous month">&larr;</button>
                <span class="fw-semibold min-w-140 text-center" id="attendanceMonthLabel">—</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="attendanceNextMonth" aria-label="Next month">&rarr;</button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div id="attendanceAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="attendance-page-full">
        <div class="content-card attendance-calendar-card">
            <div class="content-card-body border-bottom attendance-filter-bar">
                <div class="row g-3 align-items-end">
                    @if (Auth::user()->canViewAllAttendance() || Auth::user()->canViewTeamAttendance())
                    <div class="col-md-5">
                        @include('partials.employee-search-select', [
                            'inputId' => 'filterEmployeeInput',
                            'hiddenId' => 'filterEmployeeId',
                        ])
                    </div>
                    @endif
                    <div class="col-md-4">
                        <label for="filterStatus" class="form-label">Day Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All days</option>
                            <option value="present">Present</option>
                            <option value="half_day">Half Day</option>
                            <option value="absent">Absent</option>
                            <option value="weekly_off">Weekly Off</option>
                            <option value="holiday">Holiday</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>
                    </div>
                </div>
            </div>

            <div class="content-card-body">
                    <div class="attendance-summary-row mb-3" id="attendanceSummaryRow">
                        <div class="attendance-summary-item attendance-summary-item--present">
                            <span class="attendance-summary-dot"></span>
                            <span id="attendancePresentCount">0</span> present
                        </div>
                        <div class="attendance-summary-item attendance-summary-item--half-day">
                            <span class="attendance-summary-dot"></span>
                            <span id="attendanceHalfDayCount">0</span> half day
                        </div>
                        <div class="attendance-summary-item attendance-summary-item--absent">
                            <span class="attendance-summary-dot"></span>
                            <span id="attendanceAbsentCount">0</span> absent
                        </div>
                        <div class="attendance-summary-item attendance-summary-item--weekly-off">
                            <span class="attendance-summary-dot"></span>
                            <span id="attendanceWeeklyOffCount">0</span> weekly off
                        </div>
                        <div class="attendance-summary-item attendance-summary-item--holiday">
                            <span class="attendance-summary-dot"></span>
                            <span id="attendanceHolidayCount">0</span> holiday
                        </div>
                        <div class="attendance-summary-item attendance-summary-item--on-leave">
                            <span class="attendance-summary-dot"></span>
                            <span id="attendanceOnLeaveCount">0</span> on leave
                        </div>
                        <div class="attendance-summary-item ms-auto text-muted" id="attendanceRequiredHours">Required: —</div>
                    </div>

                    <div class="attendance-policy-info d-none" id="attendancePolicyInfo"></div>

                    <div class="attendance-month-holidays d-none" id="attendanceMonthHolidays">
                        <div class="attendance-month-holidays-head">
                            <h2 class="attendance-month-holidays-title">Holidays this month</h2>
                            <a href="{{ route('web.masters.attendance.holidays.index') }}" class="btn btn-sm btn-outline-primary d-none" id="attendanceManageHolidaysLink">Manage holidays</a>
                        </div>
                        <div class="attendance-month-holidays-list" id="attendanceMonthHolidaysList"></div>
                    </div>

                    <div class="attendance-cal-shell">
                        <div class="attendance-cal-toolbar">
                            <div class="dropdown attendance-cal-legends-dropdown ms-auto">
                                <button class="attendance-cal-legends-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    Legends
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end attendance-cal-legends-menu">
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--present">Present full day</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--half-day">Half day</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--absent">Absent full day</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--weekly-off">Weekly off</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--holiday">Holiday</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--on-leave">On leave</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--regularization">Regularization pending</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--joining">Joining date</span></li>
                                    <li><span class="dropdown-item-text attendance-legend-item attendance-legend-item--punch">Punch in / out</span></li>
                                </ul>
                            </div>
                        </div>

                        <div class="attendance-calendar-grid" id="attendanceCalendarGrid">
                            <table class="attendance-calendar-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Mon</th>
                                        <th scope="col">Tue</th>
                                        <th scope="col">Wed</th>
                                        <th scope="col">Thu</th>
                                        <th scope="col">Fri</th>
                                        <th scope="col">Sat</th>
                                        <th scope="col">Sun</th>
                                    </tr>
                                </thead>
                                <tbody id="attendanceCalendarDays">
                                    <tr>
                                        <td colspan="7" class="attendance-calendar-loading">Loading calendar...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="attendanceDayModal" tabindex="-1" aria-labelledby="attendanceDayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="attendanceDayModalLabel">Attendance Details</h5>
                        <div class="small text-muted" id="attendanceDayModalSubtitle">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="attendanceDayModalBody">
                    <div class="text-center text-muted py-4">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/attendance.js'])
@endsection
