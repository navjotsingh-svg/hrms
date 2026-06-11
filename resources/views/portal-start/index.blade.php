@extends('layouts.app')

@section('title', 'Start Portal Day - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Start Portal Day</h1>
            <p class="page-subtitle mb-0">Set when company attendance tracking begins. Unmarked working days after that show as absent.</p>
        </div>
        <a href="{{ route('web.attendance.index') }}" class="btn btn-outline-secondary">View Attendance</a>
    </div>
@endsection

@section('content')
    <div id="portalStartAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card">
        <div class="content-card-body">
            <form id="portalStartForm" class="row g-4">
                <div class="col-lg-8">
                    <p class="text-muted mb-4">
                        Attendance is counted from this date onward for the company. Days before it stay blank on the calendar.
                        For each employee, tracking starts from the latest of this date, their portal access date,
                        and their joining date. After tracking starts, any working day without attendance is marked absent.
                    </p>

                    <div class="mb-3">
                        <label for="attendance_portal_start_date" class="form-label">Portal start date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="attendance_portal_start_date"
                            name="attendance_portal_start_date"
                            max="9999-12-31"
                        >
                        <div class="form-text">Use a 4-digit year (YYYY-MM-DD).</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="small text-muted mb-4" id="portalStartStatus">Loading current setting...</div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" id="portalStartSubmitBtn">Save Portal Start Day</button>
                        <button type="button" class="btn btn-outline-secondary" id="portalStartClearBtn">Clear</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/portal-start.js'])
@endsection
