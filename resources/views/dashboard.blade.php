@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="dash-home-greeting mb-0">Hello, <span id="dashboardHelloName">{{ strtok(Auth::user()->name, ' ') }}</span>!</h1>
        <span id="dashboardLastUpdated" class="text-muted small d-none">Updated just now</span>
    </div>
@endsection

@section('content')
    <div id="dashboardHomeRoot" class="d-none">
        <div id="dashboardHomeWidgets" class="row g-3 mb-4 d-none"></div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="dash-home-card dash-celebrations-card">
                    <ul class="nav nav-tabs dash-home-tabs" id="dashboardCelebrationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dash-birthdays-tab" data-bs-toggle="tab" data-bs-target="#dashBirthdaysPane" type="button" role="tab">Birthdays</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="dash-anniversaries-tab" data-bs-toggle="tab" data-bs-target="#dashAnniversariesPane" type="button" role="tab">Anniversaries</button>
                        </li>
                    </ul>
                    <div class="tab-content dash-celebrations-body" id="dashboardCelebrationTabContent">
                        <div class="tab-pane fade show active" id="dashBirthdaysPane" role="tabpanel">
                            <div id="dashboardBirthdaysToday"></div>
                            <div id="dashboardBirthdaysUpcoming"></div>
                        </div>
                        <div class="tab-pane fade" id="dashAnniversariesPane" role="tabpanel">
                            <div id="dashboardAnniversariesUpcoming"></div>
                        </div>
                    </div>
                </div>

                <div class="dash-home-card mt-4" id="dashboardPendingCard">
                    <div class="dash-home-card-header">
                        <h2 class="dash-home-card-title">Pending things to do</h2>
                    </div>
                    <div class="dash-home-card-body p-0">
                        <div class="dash-pending-section">
                            <button class="dash-pending-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardPendingCollapse" aria-expanded="true">
                                <span>Request Approvals</span>
                                <span class="dash-pending-count badge text-bg-warning d-none" id="dashboardPendingCount">0</span>
                            </button>
                            <div class="collapse show" id="dashboardPendingCollapse">
                                <div class="table-responsive" id="dashboardPendingTableWrap">
                                    <table class="table dash-pending-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Request By</th>
                                                <th>Employee ID</th>
                                                <th>Request Type</th>
                                                <th>Requested On</th>
                                                <th>Request Status</th>
                                                <th class="text-end">Details</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dashboardPendingBody"></tbody>
                                    </table>
                                </div>
                                <div class="dash-pending-empty d-none" id="dashboardPendingEmpty">
                                    Well done. No request approvals.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="dash-home-card dash-clock-card">
                    <div class="dash-home-card-header border-0 pb-0">
                        <h2 class="dash-home-card-title h6 text-muted mb-2">Time &amp; Attendance</h2>
                    </div>
                    <div class="dash-home-card-body text-center">
                        <div class="dash-clock-date" id="dashboardClockDate">{{ now()->format('D M d Y') }}</div>
                        <div class="dash-clock-time" id="dashboardClockTime">{{ now()->format('h:i:s A') }}</div>
                        <div class="dash-clock-timezone text-muted small" id="dashboardClockTimezone">(GMT+0530) IST – Asia/Kolkata</div>

                        <div id="dashboardPunchWrap" class="d-none mt-3">
                            <div id="dashboardAttendanceAlert" class="alert alert-success alert-dismissible fade show d-none mb-3 text-start" role="alert"></div>
                            @include('attendance.partials.punch-widget', ['prefix' => 'dashboard'])
                        </div>
                    </div>
                </div>

                <div class="dash-home-card mt-4">
                    <div class="dash-home-card-header">
                        <h2 class="dash-home-card-title h6">Quick Actions</h2>
                    </div>
                    <div class="dash-home-card-body">
                        <div class="dash-quick-actions" id="dashboardQuickActions"></div>
                    </div>
                </div>

                <div class="dash-home-card mt-4">
                    <div class="dash-home-card-header">
                        <h2 class="dash-home-card-title h6" id="dashboardNewJoineesTitle">New Joinees</h2>
                    </div>
                    <div class="dash-home-card-body">
                        <div class="dash-people-scroll" id="dashboardNewJoinees"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="dashboardLoadingState" class="dashboard-loading-state">Loading dashboard...</div>

    @vite(['resources/js/dashboard.js'])
@endsection
