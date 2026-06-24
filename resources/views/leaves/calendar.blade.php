@extends('layouts.app')

@section('title', 'Leave Calendar - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Leave Calendar</h1>
            <p class="page-subtitle mb-0">View all approved employee leaves by month.</p>
        </div>
        <a href="{{ route('web.leave.index') }}" class="btn btn-outline-secondary">Back to Leave Management</a>
    </div>
@endsection

@section('content')
    <div id="leaveCalendarAlert" class="alert alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card leave-calendar-card">
        <div class="content-card-body border-bottom leave-calendar-toolbar p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="button" class="btn btn-link px-0 text-decoration-none" id="leaveCalendarTodayBtn">View Today</button>
                    <div class="leave-calendar-nav d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm leave-calendar-nav-btn" id="leaveCalendarPrevBtn" aria-label="Previous month">&lsaquo;</button>
                        <h2 class="h5 mb-0 leave-calendar-month-label" id="leaveCalendarMonthLabel">—</h2>
                        <button type="button" class="btn btn-outline-secondary btn-sm leave-calendar-nav-btn" id="leaveCalendarNextBtn" aria-label="Next month">&rsaquo;</button>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="leaveCalendarShowHolidays" checked>
                        <label class="form-check-label" for="leaveCalendarShowHolidays">Show holidays</label>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Legends
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-3 leave-calendar-legends-menu" id="leaveCalendarLegends"></div>
                    </div>
                </div>
            </div>
            <div class="small text-muted mt-2" id="leaveCalendarSummary"></div>
        </div>

        <div class="leave-calendar-grid-wrap p-3 pt-0">
            <div class="leave-calendar-weekdays">
                <div>Mon</div>
                <div>Tue</div>
                <div>Wed</div>
                <div>Thu</div>
                <div>Fri</div>
                <div>Sat</div>
                <div>Sun</div>
            </div>
            <div class="leave-calendar-grid" id="leaveCalendarGrid">
                <div class="leave-calendar-loading text-muted">Loading calendar…</div>
            </div>
        </div>
    </div>
@endsection
