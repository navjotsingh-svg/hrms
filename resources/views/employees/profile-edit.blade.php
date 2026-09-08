@extends('layouts.app')

@section('title', 'Edit Employee Profile - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <nav aria-label="breadcrumb" class="mb-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('web.employees.index') }}">Employees</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('web.employees.show', ['employee' => $employeeId]) }}">Profile</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
            <h1 class="page-title mb-1">Edit Employee Profile</h1>
            <p class="page-subtitle mb-0">Update personal, bank, compliance, salary revisions, and document details. Changes save immediately without approval.</p>
        </div>
        <a href="{{ route('web.employees.show', ['employee' => $employeeId]) }}" class="btn btn-outline-secondary">Back to Profile</a>
    </div>
@endsection

@section('content')
    <div class="profile-dashboard-grid">
        <aside class="profile-dashboard-sidebar">
            @include('profile.partials.header')
        </aside>

        <div class="profile-dashboard-main">
            <div class="content-card profile-page-card">
        <div class="profile-tab-nav-wrap">
            <ul class="nav nav-tabs profile-tab-nav" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-work-tab" data-bs-toggle="tab" data-bs-target="#profileWorkPane" type="button" role="tab">Work</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-personal-tab" data-bs-toggle="tab" data-bs-target="#profilePersonalPane" type="button" role="tab">Personal</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-salary-tab" data-bs-toggle="tab" data-bs-target="#profileSalaryPane" type="button" role="tab">Salary</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-bank-tab" data-bs-toggle="tab" data-bs-target="#profileBankPane" type="button" role="tab">Bank</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-compliances-tab" data-bs-toggle="tab" data-bs-target="#profileCompliancesPane" type="button" role="tab">Compliances</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-documents-tab" data-bs-toggle="tab" data-bs-target="#profileDocumentsPane" type="button" role="tab">Documents</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-journey-tab" data-bs-toggle="tab" data-bs-target="#profileJourneyPane" type="button" role="tab">Portal Journey</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="profile-other-tab" data-bs-toggle="tab" data-bs-target="#profileOtherPane" type="button" role="tab">Other</button>
                </li>
            </ul>
        </div>

        <div class="tab-content profile-tab-content" id="profileTabContent">
            <div class="tab-pane fade show active" id="profileWorkPane" role="tabpanel">
                @include('profile.partials.tabs.work')
            </div>
            <div class="tab-pane fade" id="profilePersonalPane" role="tabpanel">
                @include('profile.partials.tabs.personal', ['hideAccountSettings' => true])
            </div>
            <div class="tab-pane fade" id="profileSalaryPane" role="tabpanel">
                @include('profile.partials.tabs.salary')
            </div>
            <div class="tab-pane fade" id="profileBankPane" role="tabpanel">
                @include('profile.partials.tabs.bank')
            </div>
            <div class="tab-pane fade" id="profileCompliancesPane" role="tabpanel">
                @include('profile.partials.tabs.compliances')
            </div>
            <div class="tab-pane fade" id="profileDocumentsPane" role="tabpanel">
                @include('profile.partials.tabs.documents')
            </div>
            <div class="tab-pane fade" id="profileJourneyPane" role="tabpanel">
                @include('profile.partials.tabs.journey')
            </div>
            <div class="tab-pane fade" id="profileOtherPane" role="tabpanel">
                @include('profile.partials.tabs.other')
            </div>
        </div>
            </div>
        </div>
    </div>

    <script>window.PROFILE_TARGET_EMPLOYEE_ID = @json($employeeId);</script>
    @vite(['resources/js/profile.js'])
@endsection
