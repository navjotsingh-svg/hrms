@extends('layouts.app')

@section('title', 'People - ' . config('app.name', 'HRMS'))

@section('header')
    <div>
        <h1 class="page-title mb-1">People</h1>
        <p class="page-subtitle mb-0">Employee directory and organization structure.</p>
    </div>
@endsection

@section('content')
    <div id="peopleAlert" class="alert alert-danger alert-dismissible fade show d-none" role="alert"></div>

    <div class="content-card people-page-card">
        <div class="people-tab-nav-wrap border-bottom">
            <ul class="nav nav-tabs people-tab-nav" id="peopleTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="people-summary-tab" data-bs-toggle="tab" data-bs-target="#peopleSummaryPane" type="button" role="tab">Summary</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="people-org-chart-tab" data-bs-toggle="tab" data-bs-target="#peopleOrgChartPane" type="button" role="tab">Org Chart</button>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="peopleSummaryPane" role="tabpanel">
                <div class="people-search-wrap">
                    <div class="people-search-field">
                        <input type="search" class="form-control" id="peopleSearch" placeholder="Search by Name, Employee ID, Department or Designation" autocomplete="off">
                        <span class="people-search-icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242.656a5 5 0 1 1 0-10 5 5 0 0 1 0 10"/></svg>
                        </span>
                    </div>
                </div>

        @include('partials.list-pagination-header', ['perPageId' => 'peoplePerPage'])
        <div class="table-responsive">
                    <table class="table people-summary-table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Employee ID</th>
                                <th>Department</th>
                            </tr>
                        </thead>
                        <tbody id="peopleSummaryBody">
                            <tr><td colspan="3" class="text-center text-muted py-5">Loading people...</td></tr>
                        </tbody>
                    </table>
                </div>

                @include('partials.list-pagination-footer', [
                    'infoId' => 'peoplePaginationInfo',
                    'listId' => 'peoplePaginationList',
                    'perPageId' => 'peoplePerPage',
                    'wrapClass' => 'people-pagination-bar',
                    'ariaLabel' => 'People pagination',
                    'infoText' => '—',
                ])
            </div>

            <div class="tab-pane fade" id="peopleOrgChartPane" role="tabpanel">
                <div class="org-chart-wrap" id="peopleOrgChartRoot">
                    <div class="text-center text-muted py-5">Loading organization chart...</div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/people.js'])
@endsection
