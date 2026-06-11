@extends('layouts.app')

@section('title', 'Company Details - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-3">
            <div class="company-header-logo-wrap" data-field="logo-wrap">
                <div class="company-detail-logo-default">—</div>
            </div>
            <div>
                <h1 class="page-title mb-1" data-field="title">Loading...</h1>
                <p class="page-subtitle mb-0" data-field="subtitle">Please wait</p>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="table-action-group">
                <a href="{{ route('web.companies.edit', $company) }}" class="table-action-btn table-action-btn--edit" title="Edit" aria-label="Edit company">
                    @include('partials.icons.edit')
                </a>
            </div>
            <a href="{{ route('web.companies.index') }}" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="companyShowRoot" data-company-id="{{ $company->id }}">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Company Details</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-label">Email</div>
                                <div class="detail-value" data-field="email">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value" data-field="phone">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">Website</div>
                                <div class="detail-value" data-field="website">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label">Industry</div>
                                <div class="detail-value" data-field="industry">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-label">Founded Year</div>
                                <div class="detail-value" data-field="founded_year">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-label">Employee Strength</div>
                                <div class="detail-value" data-field="employee_strength">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-label">Timezone</div>
                                <div class="detail-value" data-field="timezone">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Legal & Tax</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="detail-label">Registration No.</div>
                                <div class="detail-value" data-field="registration_number">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-label">GSTIN</div>
                                <div class="detail-value" data-field="gstin">—</div>
                            </div>
                            <div class="col-md-4">
                                <div class="detail-label">PAN</div>
                                <div class="detail-value" data-field="pan_number">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Address</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="detail-value" data-field="address">—</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Status</h5>
                    </div>
                    <div class="content-card-body">
                        <span class="badge bg-secondary fs-6" data-field="status-badge">—</span>
                    </div>
                </div>

                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Login Account</h5>
                    </div>
                    <div class="content-card-body">
                        <p class="text-muted mb-0 d-none" data-field="admin_empty">No login account linked.</p>
                        <div class="mb-2">
                            <div class="detail-label">Admin Name</div>
                            <div class="detail-value" data-field="admin_name">—</div>
                        </div>
                        <div>
                            <div class="detail-label">Login Email</div>
                            <div class="detail-value" data-field="admin_email">—</div>
                        </div>
                    </div>
                </div>

                <div class="content-card mb-4">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Contact Person</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="mb-2">
                            <div class="detail-label">Name</div>
                            <div class="detail-value" data-field="contact_person_name">—</div>
                        </div>
                        <div class="mb-2">
                            <div class="detail-label">Email</div>
                            <div class="detail-value" data-field="contact_person_email">—</div>
                        </div>
                        <div>
                            <div class="detail-label">Phone</div>
                            <div class="detail-value" data-field="contact_person_phone">—</div>
                        </div>
                    </div>
                </div>

                <div class="content-card d-none" data-field="description-card">
                    <div class="content-card-header">
                        <h5 class="content-card-title">Company Description</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="company-description-content text-muted" data-field="description"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('companies.partials.logo-lightbox')
    @vite(['resources/js/company-show.js'])
@endsection
