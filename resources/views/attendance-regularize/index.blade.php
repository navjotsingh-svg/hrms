@extends('layouts.app')



@section('title', 'Attendance Regularization - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Attendance Regularization</h1>

            <p class="page-subtitle mb-0">Select one or more dates with incomplete attendance and submit a correction request.</p>

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

                <p class="small text-muted mb-0">Select multiple dates below, then submit one regularization request for all of them.</p>

            </div>

            @if (Auth::user()->canViewAllAttendance())

            <div class="regularize-employee-filter">
                @include('partials.employee-search-select', [
                    'inputId' => 'eligibleEmployeeInput',
                    'hiddenId' => 'eligibleEmployeeId',
                    'inputClass' => 'form-control-sm',
                ])
            </div>

            @endif

            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllEligibleBtn">Select all</button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="clearEligibleSelectionBtn">Clear</button>
                <button type="button" class="btn btn-primary btn-sm" id="openRegularizeRequestBtn" disabled>Regularize selected (0)</button>
            </div>

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

            <div>

                <h2 class="content-card-title mb-0">Pending Approvals</h2>

                <p class="small text-muted mb-0">Review single-day or multi-day attendance correction requests.</p>

            </div>

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



    @vite(['resources/js/attendance-regularize.js'])

@endsection


