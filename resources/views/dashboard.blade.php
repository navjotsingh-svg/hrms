@extends('layouts.app')

@section('title', 'Home - ' . config('app.name', 'HRMS'))

@php
    $user = Auth::user();
    $showMoments = $user->canSeeMenu('home.moments');
    $showAnalytics = $user->canSeeMenu('home.dashboard');
@endphp

@section('header')
    <div class="home-hero">
        <div class="home-hero__content">
            <div class="home-hero__eyebrow">{{ now()->format('l, F j') }}</div>
            <h1 class="home-hero__title">
                Hello, <span id="dashboardHelloName">{{ strtok($user->name, ' ') }}</span>
            </h1>
            <p class="home-hero__subtitle">Your workspace for updates, team moments, and insights.</p>
        </div>
        <div class="home-hero__meta">
            <span id="dashboardLastUpdated" class="home-hero__updated d-none">Updated just now</span>
        </div>
    </div>
@endsection

@section('content')
    <div class="home-unified-page">
        @if ($showMoments || $showAnalytics)
            <nav class="home-section-nav" aria-label="Home sections">
                <a href="#home-overview" class="home-section-nav__link is-active">
                    <span class="home-section-nav__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/></svg>
                    </span>
                    Overview
                </a>
                @if ($showMoments)
                    <a href="#home-moments" class="home-section-nav__link">
                        <span class="home-section-nav__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 1a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l4.586-4.586a1 1 0 0 0 0-1.414l-7-7A1 1 0 0 0 6.586 1zm4 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/></svg>
                        </span>
                        Moments
                    </a>
                @endif
                @if ($showAnalytics)
                    <a href="#home-analytics" class="home-section-nav__link">
                        <span class="home-section-nav__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 11H2v3h2zm5-4H7v7h2zm5-5v12h-2V2z"/></svg>
                        </span>
                        Analytics
                    </a>
                @endif
            </nav>
        @endif

        <section id="home-overview" class="home-unified-section">
            <div id="dashboardLoadingState" class="dashboard-loading-state">
                <span class="dashboard-loading-spinner" aria-hidden="true"></span>
                <span>Loading your dashboard…</span>
            </div>

            <div id="dashboardHomeRoot" class="d-none">
                <div class="row g-4 home-overview-grid">
                    <div class="col-xl-8">
                        <div class="dash-home-card dash-celebrations-card">
                            <div class="home-panel-accent home-panel-accent--celebrations"></div>
                            <ul class="nav nav-tabs dash-home-tabs" id="dashboardCelebrationTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="dash-birthdays-tab" data-bs-toggle="tab" data-bs-target="#dashBirthdaysPane" type="button" role="tab">Birthdays</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="dash-anniversaries-tab" data-bs-toggle="tab" data-bs-target="#dashAnniversariesPane" type="button" role="tab">Anniversaries</button>
                                </li>
                            </ul>
                            <div class="tab-content dash-celebrations-body" id="dashboardCelebrationTabContent">
                                <div class="tab-pane fade show active dash-celebration-pane dash-celebration-pane--birthdays" id="dashBirthdaysPane" role="tabpanel">
                                    <div class="dash-celebration-decor" aria-hidden="true">
                                        <span class="dash-celebration-decor__item dash-celebration-decor__balloon dash-celebration-decor__balloon--1"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__balloon dash-celebration-decor__balloon--2"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__balloon dash-celebration-decor__balloon--3"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__cake"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__confetti dash-celebration-decor__confetti--1"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__confetti dash-celebration-decor__confetti--2"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__confetti dash-celebration-decor__confetti--3"></span>
                                    </div>
                                    <div class="dash-celebration-content">
                                        <div id="dashboardBirthdaysToday"></div>
                                        <div id="dashboardBirthdaysUpcoming"></div>
                                    </div>
                                </div>
                                <div class="tab-pane fade dash-celebration-pane dash-celebration-pane--anniversaries" id="dashAnniversariesPane" role="tabpanel">
                                    <div class="dash-celebration-decor" aria-hidden="true">
                                        <span class="dash-celebration-decor__item dash-celebration-decor__star dash-celebration-decor__star--1"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__star dash-celebration-decor__star--2"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__medal"></span>
                                        <span class="dash-celebration-decor__item dash-celebration-decor__ribbon"></span>
                                    </div>
                                    <div class="dash-celebration-content">
                                        <div id="dashboardAnniversariesUpcoming"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dash-home-card mt-4" id="dashboardPendingCard">
                            <div class="home-panel-accent home-panel-accent--pending"></div>
                            <div class="dash-home-card-header home-panel-header">
                                <div>
                                    <h2 class="dash-home-card-title">Pending things to do</h2>
                                    <p class="home-panel-subtitle mb-0">Review and action items that need your attention</p>
                                </div>
                            </div>
                            <div class="dash-home-card-body p-0">
                                <div class="dash-pending-section">
                                    <div class="home-pending-toolbar">
                                        <button class="home-pending-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#dashboardPendingCollapse" aria-expanded="true">
                                            <span class="home-pending-toggle__label">
                                                Request Approvals
                                                <span class="dash-pending-count badge text-bg-warning d-none" id="dashboardPendingCount">0</span>
                                            </span>
                                            <span class="home-pending-toggle__chevron" aria-hidden="true"></span>
                                        </button>
                                        <a href="{{ route('web.requests.index', ['tab' => 'approval']) }}" class="btn btn-sm btn-hrms-outline">View all</a>
                                    </div>
                                    <div class="collapse show" id="dashboardPendingCollapse">
                                        <div class="dash-pending-bulk d-none align-items-center gap-2 px-3 py-2 border-bottom bg-light" id="dashboardPendingBulkBar">
                                            <span class="small text-muted" id="dashboardPendingSelectedCount">0 selected</span>
                                            <button type="button" class="btn btn-sm btn-success" id="dashboardPendingBulkApprove">Approve selected</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" id="dashboardPendingBulkReject">Reject selected</button>
                                        </div>
                                        @include('partials.list-pagination-top', [
                                            'infoId' => 'dashboardPendingPaginationInfo',
                                            'listId' => 'dashboardPendingPaginationList',
                                            'perPageId' => 'dashboardPendingPerPage',
                                            'wrapId' => 'dashboardPendingPaginationWrap',
                                            'wrapClassTop' => 'home-table-pagination home-table-pagination--top d-none',
                                            'ariaLabel' => 'Pending requests pagination',
                                            'perPageOptions' => [5, 10, 25, 50],
                                            'defaultPerPage' => 5,
                                        ])
                                        <div class="table-responsive home-table-wrap" id="dashboardPendingTableWrap">
                                            <table class="table dash-pending-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th class="dash-pending-select-col">
                                                            <input type="checkbox" class="form-check-input" id="dashboardPendingSelectAll" aria-label="Select all pending requests">
                                                        </th>
                                                        <th>Request By</th>
                                                        <th>Request Type</th>
                                                        <th>Requested On</th>
                                                        <th>Request Status</th>
                                                        <th class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dashboardPendingBody"></tbody>
                                            </table>
                                        </div>
                                        @include('partials.list-pagination-footer', [
                                            'infoId' => 'dashboardPendingPaginationInfo',
                                            'listId' => 'dashboardPendingPaginationList',
                                            'perPageId' => 'dashboardPendingPerPage',
                                            'wrapId' => 'dashboardPendingPaginationWrap',
                                            'wrapClass' => 'home-table-pagination home-table-pagination--bottom d-none',
                                            'ariaLabel' => 'Pending requests pagination',
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dash-home-card mt-4 d-none" id="dashboardMyRequestsCard">
                            <div class="home-panel-accent home-panel-accent--requests"></div>
                            <div class="dash-home-card-header home-panel-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h2 class="dash-home-card-title mb-0">My Requests</h2>
                                    <p class="home-panel-subtitle mb-0">Track submissions you have raised</p>
                                </div>
                                <a href="{{ route('web.requests.index', ['tab' => 'mine']) }}" class="btn btn-sm btn-hrms-outline">View all</a>
                            </div>
                            <div class="dash-home-card-body p-0">
                                <div class="dash-pending-section border-top-0">
                                    <div class="collapse show" id="dashboardMyRequestsCollapse">
                                        @include('partials.list-pagination-top', [
                                            'infoId' => 'dashboardMyRequestsPaginationInfo',
                                            'listId' => 'dashboardMyRequestsPaginationList',
                                            'perPageId' => 'dashboardMyRequestsPerPage',
                                            'wrapId' => 'dashboardMyRequestsPaginationWrap',
                                            'wrapClassTop' => 'home-table-pagination home-table-pagination--top d-none',
                                            'ariaLabel' => 'My requests pagination',
                                        ])
                                        <div class="table-responsive home-table-wrap" id="dashboardMyRequestsTableWrap">
                                            <table class="table dash-pending-table mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Request Type</th>
                                                        <th>Details</th>
                                                        <th>Requested On</th>
                                                        <th>Status</th>
                                                        <th class="text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dashboardMyRequestsBody"></tbody>
                                            </table>
                                        </div>
                                        @include('partials.list-pagination-footer', [
                                            'infoId' => 'dashboardMyRequestsPaginationInfo',
                                            'listId' => 'dashboardMyRequestsPaginationList',
                                            'perPageId' => 'dashboardMyRequestsPerPage',
                                            'wrapId' => 'dashboardMyRequestsPaginationWrap',
                                            'wrapClass' => 'home-table-pagination home-table-pagination--bottom',
                                            'ariaLabel' => 'My requests pagination',
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 home-sidebar-stack">
                        <div class="dash-home-card dash-clock-card">
                            <div class="dash-home-card-body text-center">
                                <div class="dash-clock-badge">Time &amp; Attendance</div>
                                <div class="dash-clock-date" id="dashboardClockDate">{{ now()->format('D M d Y') }}</div>
                                <div class="dash-clock-time" id="dashboardClockTime">{{ now()->format('h:i:s A') }}</div>
                                <div class="dash-clock-timezone" id="dashboardClockTimezone">(GMT+0530) IST – Asia/Kolkata</div>

                                <div id="dashboardPunchWrap" class="d-none mt-3">
                                    <div id="dashboardAttendanceAlert" class="alert alert-success alert-dismissible fade show d-none mb-3 text-start" role="alert"></div>
                                    @include('attendance.partials.punch-widget', ['prefix' => 'dashboard'])
                                </div>
                            </div>
                        </div>

                        <div class="dash-home-card mt-4">
                            <div class="home-panel-accent home-panel-accent--actions"></div>
                            <div class="dash-home-card-header home-panel-header">
                                <h2 class="dash-home-card-title h6 mb-0">Quick Actions</h2>
                            </div>
                            <div class="dash-home-card-body">
                                <div class="dash-quick-actions" id="dashboardQuickActions"></div>
                            </div>
                        </div>

                        <div class="dash-home-card mt-4">
                            <div class="home-panel-accent home-panel-accent--joinees"></div>
                            <div class="dash-home-card-header home-panel-header">
                                <h2 class="dash-home-card-title h6 mb-0" id="dashboardNewJoineesTitle">New Joinees</h2>
                            </div>
                            <div class="dash-home-card-body">
                                <div class="dash-people-scroll" id="dashboardNewJoinees"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($showMoments)
            <section id="home-moments" class="home-unified-section">
                <div class="home-panel home-panel--moments">
                    <div class="home-panel-accent home-panel-accent--moments"></div>
                    <div class="home-unified-section-header home-panel-header">
                        <div class="home-section-heading">
                            <span class="home-section-heading__icon home-section-heading__icon--moments" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M2 1a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l4.586-4.586a1 1 0 0 0 0-1.414l-7-7A1 1 0 0 0 6.586 1zm4 3.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/></svg>
                            </span>
                            <div>
                                <h2 class="home-unified-section-title mb-1">Company Moments</h2>
                                <p class="home-panel-subtitle mb-0">Celebrate milestones, share updates, and stay connected with your team.</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-hrms-outline" id="momentsRefreshBtn">Refresh</button>
                    </div>
                    <div class="home-panel-body home-panel-body--moments">
                        @include('home.partials.moments-feed')
                    </div>
                </div>
            </section>
        @endif

        @if ($showAnalytics)
            <section id="home-analytics" class="home-unified-section">
                <div class="home-panel home-panel--analytics">
                    <div class="home-panel-accent home-panel-accent--analytics"></div>
                    <div class="home-unified-section-header home-panel-header home-analytics-toolbar">
                        <div class="home-section-heading">
                            <span class="home-section-heading__icon home-section-heading__icon--analytics" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M4 11H2v3h2zm5-4H7v7h2zm5-5v12h-2V2z"/></svg>
                            </span>
                            <div>
                                <h2 class="home-unified-section-title mb-1">Analytics Dashboard</h2>
                                <p class="home-panel-subtitle mb-0" id="homeDashboardRangeSummary">Loading period summary…</p>
                            </div>
                        </div>
                        <div class="home-analytics-controls">
                            <div class="home-dashboard-range-filter">
                                <label for="homeDashboardRangePreset" class="form-label small mb-1">Period</label>
                                <select class="form-select form-select-sm" id="homeDashboardRangePreset">
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="this_week">This Week</option>
                                    <option value="this_month" selected>This Month</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div id="homeDashboardCustomRange" class="d-none home-analytics-custom-range">
                                <div>
                                    <label for="homeDashboardFromDate" class="form-label small mb-1">From</label>
                                    <input type="date" class="form-control form-control-sm" id="homeDashboardFromDate">
                                </div>
                                <div>
                                    <label for="homeDashboardToDate" class="form-label small mb-1">To</label>
                                    <input type="date" class="form-control form-control-sm" id="homeDashboardToDate">
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" id="homeDashboardApplyRangeBtn">Apply</button>
                            </div>
                            @if ($user->hasPermission('home.dashboard.manage'))
                                <button type="button" class="btn btn-primary btn-sm" id="homeDashboardManageBtn">Add Charts</button>
                            @endif
                        </div>
                    </div>
                    <div class="home-panel-body home-panel-body--analytics">
                        @include('home.partials.analytics-dashboard')
                    </div>
                </div>
            </section>
        @endif
    </div>

    @php
        $homeScripts = ['resources/js/dashboard.js'];

        if ($showMoments) {
            $homeScripts[] = 'resources/js/moments.js';
        }

        if ($showAnalytics) {
            $homeScripts[] = 'resources/js/home-dashboard.js';
        }
    @endphp

    @vite($homeScripts)
@endsection
