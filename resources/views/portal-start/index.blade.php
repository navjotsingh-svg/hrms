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
                            required
                        >
                        <div class="form-text">Set once for the company. This date cannot be changed after saving.</div>
                        <div class="invalid-feedback"></div>
                    </div>

                    <div class="small text-muted mb-4" id="portalStartStatus">Loading current setting...</div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" id="portalStartSubmitBtn">Save Portal Start Day</button>
                    </div>
                </div>

                <div class="col-lg-8 mt-2 pt-4 border-top">
                    <h2 class="h5 mb-2">Face Verification Settings</h2>
                    <p class="text-muted mb-3">
                        Set the minimum face match percentage required when employees mark attendance.
                        Leave blank to use the system default (<span id="attendanceFaceDefaultThreshold">80</span>%).
                    </p>

                    <div class="mb-3">
                        <label for="attendance_face_match_threshold" class="form-label">Required face match (%)</label>
                        <input
                            type="number"
                            class="form-control"
                            id="attendance_face_match_threshold"
                            name="attendance_face_match_threshold"
                            min="1"
                            max="100"
                            step="1"
                            placeholder="e.g. 80"
                        >
                        <div class="form-text">Target match percentage when face recognition is enabled.</div>
                    </div>

                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="attendance_require_face_match"
                            name="attendance_require_face_match"
                        >
                        <label class="form-check-label" for="attendance_require_face_match">
                            Require face match to punch in/out
                        </label>
                        <div class="form-text">
                            When enabled, employees must match their profile photo at or above the percentage above.
                            When disabled, attendance uses a camera photo only — no face recognition runs.
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" id="attendanceFaceSaveBtn">Save Face Settings</button>
                    </div>
                </div>

                <div class="col-lg-8 mt-2 pt-4 border-top d-none" id="attendanceNetworkSettingsSection">
                    <h2 class="h5 mb-2">Attendance Network Settings</h2>
                    <p class="text-muted mb-3">
                        Optionally restrict attendance punches to approved office IP addresses. Leave blank to allow any network.
                        Each employee punch records the client IP address and device MAC address for audit.
                    </p>

                    <div class="mb-3">
                        <label for="attendance_allowed_ips" class="form-label">Allowed IP addresses</label>
                        <textarea
                            class="form-control"
                            id="attendance_allowed_ips"
                            name="attendance_allowed_ips"
                            rows="4"
                            placeholder="192.168.1.10&#10;203.0.113.25&#10;10.0.0.0/24"
                        ></textarea>
                        <div class="form-text">One IP or CIDR range per line. Employees must be on an approved network when this list is configured.</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" id="attendanceNetworkSaveBtn">Save Network Settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @vite(['resources/js/portal-start.js'])
@endsection
