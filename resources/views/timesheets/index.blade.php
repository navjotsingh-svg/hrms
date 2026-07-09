@extends('layouts.app')



@section('title', 'Timesheets - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Timesheets</h1>

            <p class="page-subtitle mb-0">

                @if ($canSubmitTimesheets && $canReviewTeamTimesheets)

                    Submit daily project reports and review your team’s submissions by period.

                @elseif ($canReviewTeamTimesheets)

                    Review your team’s daily reports by employee and date range.

                @else

                    Log time and status per project. Filter by period to view or submit reports.

                @endif

            </p>

        </div>

    </div>

@endsection



@section('content')

    <div id="timesheetsAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>



    <div class="content-card mb-4">

        <div class="content-card-body">

            <div class="row g-3 align-items-end">

                @if ($canReviewTeamTimesheets)

                <div class="col-md-4">

                    <label for="teamEmployeeSelect" class="form-label">View timesheet for</label>

                    <select class="form-select" id="teamEmployeeSelect">

                        @if ($canSubmitTimesheets && $ownEmployeeId)

                            <option value="{{ $ownEmployeeId }}" selected>My timesheet</option>

                        @else

                            <option value="">Select team member...</option>

                        @endif

                    </select>

                </div>

                @endif

                <div class="col-md-3">

                    <label for="timesheetRangePreset" class="form-label">Period</label>

                    <select class="form-select" id="timesheetRangePreset">

                        <option value="today" selected>Today</option>

                        <option value="yesterday">Yesterday</option>

                        <option value="this_week">This week</option>

                        <option value="this_month">This month</option>

                        <option value="custom">Custom</option>

                    </select>

                </div>

                <div class="col-md-3 d-none" id="timesheetProjectFilterWrap">

                    <label for="timesheetProjectFilter" class="form-label">Project</label>

                    <select class="form-select" id="timesheetProjectFilter">

                        <option value="">All projects</option>

                    </select>

                </div>

                <div id="timesheetCustomRange" class="col-md-5 d-none">

                    <div class="row g-2 align-items-end">

                        <div class="col-md-4">

                            <label for="timesheetFromDate" class="form-label">From</label>

                            <input type="date" class="form-control" id="timesheetFromDate">

                        </div>

                        <div class="col-md-4">

                            <label for="timesheetToDate" class="form-label">To</label>

                            <input type="date" class="form-control" id="timesheetToDate">

                        </div>

                        <div class="col-md-4">

                            <button type="button" class="btn btn-primary w-100" id="timesheetApplyRangeBtn">Apply</button>

                        </div>

                    </div>

                </div>

            </div>

            <p class="text-muted small mb-0 mt-3" id="timesheetRangeSummary"></p>

        </div>

    </div>



    <div class="content-card mb-4" id="dailyReportCard">

        <div class="content-card-header border-bottom">

            <h2 class="content-card-title mb-0" id="dailyReportTitle">Daily Report</h2>

        </div>

        <div class="content-card-body">

            <form id="timesheetForm" novalidate>

                <div class="mb-4">

                    <div class="timesheet-day-summary" id="daySummary">

                        <span class="text-muted">Select a period to view or submit your report.</span>

                    </div>

                </div>



                <div id="timesheetFormAlert" class="alert alert-danger d-none" role="alert"></div>

                <div id="noProjectsNotice" class="alert alert-warning d-none" role="alert">

                    You are not assigned to any active projects yet. You can still log time under <strong>Other</strong> for non-project work.

                </div>

                <div id="readOnlyNotice" class="alert alert-info d-none" role="alert">

                    You are viewing a team member's report for the selected work date.

                </div>



                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" id="timesheetRowActions">

                    <button type="button" class="btn btn-outline-secondary" id="addTimesheetRowBtn">+ Add project</button>

                </div>



                <div id="timesheetEntriesBody" class="timesheet-entries-list">

                    <div class="text-muted py-4 text-center border rounded" data-placeholder-row="1">

                        Add at least one project to submit your report for the selected date.

                    </div>

                </div>



                <div id="timesheetProjectDiscussions" class="mt-4 d-none">

                    <h3 class="h6 text-uppercase text-muted mb-3" id="timesheetDiscussionsTitle">Manager comments</h3>

                    <div id="timesheetProjectDiscussionsList"></div>

                </div>



                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4" id="timesheetFormActions">

                    <button type="submit" class="btn btn-primary" id="submitTimesheetBtn">Submit</button>

                </div>

            </form>

        </div>

    </div>



    <div class="content-card d-none" id="periodReportsCard">

        <div class="content-card-header border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">

            <h2 class="content-card-title mb-0">Period Reports</h2>

            <span class="badge text-bg-light border" id="periodReportsSummary"></span>

        </div>

        <div class="content-card-body" id="periodReportsContainer">

            <div class="text-muted py-3">Loading reports...</div>

        </div>

    </div>



    <script>

        window.TIMESHEET_PAGE = {

            canSubmit: @json($canSubmitTimesheets),

            canReviewTeam: @json($canReviewTeamTimesheets),

            ownEmployeeId: @json($ownEmployeeId),

        };

    </script>

    @vite(['resources/js/timesheets-index.js'])

@endsection

