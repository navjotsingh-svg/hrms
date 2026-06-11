@extends('layouts.app')



@section('title', 'Attendance Regularization - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Attendance Regularization</h1>

            <p class="page-subtitle mb-0">Select a date with incomplete attendance and submit a correction request.</p>

        </div>

        <div class="d-flex gap-2">

            <a href="{{ route('web.attendance.index') }}" class="btn btn-outline-secondary">View Attendance</a>

        </div>

    </div>

@endsection



@section('content')

    <div id="regularizeAlert" class="alert alert-success alert-dismissible fade show d-none"></div>



    @if (Auth::user()->canRegularizeAttendance())

    <div class="content-card mb-4">

        <div class="content-card-header border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">

            <div>

                <h2 class="content-card-title mb-0">Dates Needing Regularization</h2>

                <p class="small text-muted mb-0">Last 30 working days where attendance is not fully present.</p>

            </div>

            @if (Auth::user()->canViewAllAttendance())

            <div class="regularize-employee-filter">

                <label for="eligibleEmployee" class="form-label small mb-1">Employee</label>

                <select class="form-select form-select-sm" id="eligibleEmployee">

                    <option value="">Loading...</option>

                </select>

            </div>

            @endif

        </div>

        <div class="content-card-body">

            <div id="eligibleDatesContainer" class="regularize-eligible-list">

                <div class="text-muted py-3">Loading eligible dates...</div>

            </div>

        </div>

    </div>

    @endif



    @if (Auth::user()->canApproveRegularization())

    <div class="content-card mb-4">

        <div class="content-card-header border-bottom">

            <h2 class="content-card-title mb-0">Pending Approvals</h2>

        </div>

        <div class="content-card-body">

            <div id="pendingRegularizeContainer">

                <div class="text-muted py-3">Loading pending requests...</div>

            </div>

        </div>

    </div>

    @endif



    <div class="content-card companies-list-card">

        <div class="content-card-header border-bottom">

            <h2 class="content-card-title mb-0">Request History</h2>

        </div>

        <div class="content-card-body companies-filter-bar border-bottom">

            <div class="row g-3 align-items-end">

                <div class="col-md-3">

                    <label for="filterStatus" class="form-label">Status</label>

                    <select class="form-select" id="filterStatus">

                        <option value="">All</option>

                        <option value="pending">Pending</option>

                        <option value="approved">Approved</option>

                        <option value="rejected">Rejected</option>

                        <option value="cancelled">Cancelled</option>

                    </select>

                </div>

                <div class="col-md-3">

                    <label for="filterYear" class="form-label">Year</label>

                    <select class="form-select" id="filterYear"></select>

                </div>

                <div class="col-md-3 d-flex justify-content-end">

                    <button type="button" class="btn btn-outline-secondary" id="filterReset">Reset</button>

                </div>

            </div>

        </div>

        <div class="table-responsive">

            <table class="companies-table table mb-0">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Employee</th>

                        <th>Date</th>

                        <th>Requested Times</th>

                        <th>Reason</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody id="regularizeTableBody">

                    <tr><td colspan="7" class="text-center text-muted py-5">Loading...</td></tr>

                </tbody>

            </table>

        </div>

        <div class="content-card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">

            <div class="small text-muted" id="regularizePaginationInfo">—</div>

            <ul class="pagination mb-0" id="regularizePaginationList"></ul>

        </div>

    </div>



    <div class="modal fade" id="regularizeModal" tabindex="-1" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <form id="regularizeForm">

                    <div class="modal-header">

                        <h5 class="modal-title" id="regularizeModalTitle">Request Regularization</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>

                    <div class="modal-body">

                        <input type="hidden" id="attendance_date" name="attendance_date">

                        <input type="hidden" id="regularize_employee_id" name="employee_id">



                        <div class="regularize-selected-day mb-3" id="regularizeSelectedDaySummary">—</div>



                        <div class="row g-3 mb-3">

                            <div class="col-md-6">

                                <label for="punch_in_time" class="form-label">Punch in</label>

                                <input type="time" class="form-control" id="punch_in_time" name="punch_in_time" required>

                            </div>

                            <div class="col-md-6">

                                <label for="punch_out_time" class="form-label">Punch out</label>

                                <input type="time" class="form-control" id="punch_out_time" name="punch_out_time">

                            </div>

                        </div>

                        <div class="mb-3">

                            <label for="reason" class="form-label">Reason</label>

                            <textarea class="form-control" id="reason" name="reason" rows="3" minlength="10" required placeholder="Explain why attendance was missed or needs correction"></textarea>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>

                        <button type="submit" class="btn btn-primary" id="regularizeSubmitBtn">Submit Request</button>

                    </div>

                </form>

            </div>

        </div>

    </div>



    @vite(['resources/js/attendance-regularize.js'])

@endsection


