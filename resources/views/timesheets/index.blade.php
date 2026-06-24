@extends('layouts.app')



@section('title', 'Timesheets - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Timesheets</h1>

            <p class="page-subtitle mb-0">

                @if ($canSubmitTimesheets && $canReviewTeamTimesheets)

                    Submit your daily reports and review your full team — including members added to team-lead projects.

                @elseif ($canReviewTeamTimesheets)

                    Review your full team’s daily reports and join discussions started by team leads.

                @else

                    Log your daily work by project. Submit reports for today only; past dates are view-only.

                @endif

            </p>

        </div>

    </div>

@endsection



@section('content')

    <div id="timesheetsAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>



    @if ($canReviewTeamTimesheets)

    <div class="content-card mb-4">

        <div class="content-card-body">

            <div class="row g-3 align-items-end">

                <div class="col-md-6">

                    <label for="teamEmployeeSelect" class="form-label">View timesheet for</label>

                    <select class="form-select" id="teamEmployeeSelect">

                        @if ($canSubmitTimesheets && $ownEmployeeId)

                            <option value="{{ $ownEmployeeId }}" selected>My timesheet</option>

                        @else

                            <option value="">Select team member...</option>

                        @endif

                    </select>

                </div>

            </div>

        </div>

    </div>

    @endif



    <div class="content-card mb-4">

        <div class="content-card-header border-bottom">

            <h2 class="content-card-title mb-0" id="dailyReportTitle">Daily Report</h2>

        </div>

        <div class="content-card-body">

            <form id="timesheetForm" novalidate>

                <div class="row g-3 align-items-end mb-4">

                    <div class="col-md-4">

                        <label for="workDate" class="form-label">Work date</label>

                        <input type="date" class="form-control" id="workDate" required>

                    </div>

                    <div class="col-md-8">

                        <div class="timesheet-day-summary" id="daySummary">

                            <span class="text-muted">Select a date to view or submit your report.</span>

                        </div>

                    </div>

                </div>



                <div id="timesheetFormAlert" class="alert alert-danger d-none" role="alert"></div>

                <div id="noProjectsNotice" class="alert alert-warning d-none" role="alert">

                    You are not assigned to any active projects yet. Ask your manager to add you to a project before submitting timesheets.

                </div>

                <div id="readOnlyNotice" class="alert alert-info d-none" role="alert">
                    You are viewing a team member's report. Add feedback on each project submission below.
                </div>

                <div class="table-responsive">

                    <table class="table align-middle mb-0" id="timesheetEntriesTable">

                        <thead>

                            <tr>

                                <th style="min-width: 220px;">Project</th>

                                <th style="min-width: 130px;">Start time</th>

                                <th style="min-width: 130px;">End time</th>

                                <th style="min-width: 100px;">Hours</th>

                                <th style="min-width: 200px;">Notes</th>

                                <th class="text-end" style="width: 60px;"></th>

                            </tr>

                        </thead>

                        <tbody id="timesheetEntriesBody">

                            <tr>

                                <td colspan="6" class="text-muted py-4 text-center">Add at least one project row to submit your day report.</td>

                            </tr>

                        </tbody>

                    </table>

                </div>

                <div id="timesheetProjectDiscussions" class="mt-4 d-none">
                    <h3 class="h6 text-uppercase text-muted mb-3">Project feedback</h3>
                    <div id="timesheetProjectDiscussionsList"></div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3" id="timesheetFormActions">

                    <button type="button" class="btn btn-outline-secondary" id="addTimesheetRowBtn">+ Add project</button>

                    <button type="submit" class="btn btn-primary" id="submitTimesheetBtn">Submit day report</button>

                </div>

            </form>

        </div>

    </div>

    <div class="content-card">

        <div class="content-card-header border-bottom">

            <h2 class="content-card-title mb-0">Recent Submissions</h2>

        </div>

        <div class="content-card-body" id="recentTimesheetsContainer">

            <div class="text-muted py-3">Loading recent timesheets...</div>

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


