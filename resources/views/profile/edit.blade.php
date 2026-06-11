@extends('layouts.app')

@section('title', 'Profile - ' . config('app.name', 'HRMS'))

@section('header')
    <div>
        <h1 class="page-title mb-1">My Profile</h1>
        <p class="page-subtitle mb-0">View and manage your work, personal, and compliance information.</p>
    </div>
@endsection

@section('content')
    <div class="content-card profile-page-card mb-4">
        <div class="content-card-body profile-header-wrap">
            @include('profile.partials.header')
        </div>
    </div>

    <div class="content-card profile-page-card">
        <div class="profile-tab-nav-wrap">
            <ul class="nav nav-tabs profile-tab-nav" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link active"
                        id="profile-work-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profileWorkPane"
                        type="button"
                        role="tab"
                        aria-controls="profileWorkPane"
                        aria-selected="true"
                    >
                        Work
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        id="profile-personal-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profilePersonalPane"
                        type="button"
                        role="tab"
                        aria-controls="profilePersonalPane"
                        aria-selected="false"
                    >
                        Personal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        id="profile-salary-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profileSalaryPane"
                        type="button"
                        role="tab"
                        aria-controls="profileSalaryPane"
                        aria-selected="false"
                    >
                        Salary
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        id="profile-bank-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profileBankPane"
                        type="button"
                        role="tab"
                        aria-controls="profileBankPane"
                        aria-selected="false"
                    >
                        Bank
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        id="profile-compliances-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profileCompliancesPane"
                        type="button"
                        role="tab"
                        aria-controls="profileCompliancesPane"
                        aria-selected="false"
                    >
                        Compliances
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        id="profile-documents-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profileDocumentsPane"
                        type="button"
                        role="tab"
                        aria-controls="profileDocumentsPane"
                        aria-selected="false"
                    >
                        Documents
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link"
                        id="profile-other-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#profileOtherPane"
                        type="button"
                        role="tab"
                        aria-controls="profileOtherPane"
                        aria-selected="false"
                    >
                        Other
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content profile-tab-content" id="profileTabContent">
            <div class="tab-pane fade show active" id="profileWorkPane" role="tabpanel" aria-labelledby="profile-work-tab" tabindex="0">
                @include('profile.partials.tabs.work')
            </div>
            <div class="tab-pane fade" id="profilePersonalPane" role="tabpanel" aria-labelledby="profile-personal-tab" tabindex="0">
                @include('profile.partials.tabs.personal')
            </div>
            <div class="tab-pane fade" id="profileSalaryPane" role="tabpanel" aria-labelledby="profile-salary-tab" tabindex="0">
                @include('profile.partials.tabs.salary')
            </div>
            <div class="tab-pane fade" id="profileBankPane" role="tabpanel" aria-labelledby="profile-bank-tab" tabindex="0">
                @include('profile.partials.tabs.bank')
            </div>
            <div class="tab-pane fade" id="profileCompliancesPane" role="tabpanel" aria-labelledby="profile-compliances-tab" tabindex="0">
                @include('profile.partials.tabs.compliances')
            </div>
            <div class="tab-pane fade" id="profileDocumentsPane" role="tabpanel" aria-labelledby="profile-documents-tab" tabindex="0">
                @include('profile.partials.tabs.documents')
            </div>
            <div class="tab-pane fade" id="profileOtherPane" role="tabpanel" aria-labelledby="profile-other-tab" tabindex="0">
                @include('profile.partials.tabs.other')
            </div>
        </div>
    </div>

    @vite(['resources/js/profile.js'])
@endsection
