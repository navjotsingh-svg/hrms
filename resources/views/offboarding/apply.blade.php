@extends('layouts.app')

@section('title', 'Submit Resignation - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1">Submit Resignation</h1>
            <p class="page-subtitle mb-0">Start your exit process by submitting a resignation request for manager/HR approval.</p>
        </div>
        <a href="{{ route('web.offboarding.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
@endsection

@section('content')
    <div id="offboardingApplyAlert" class="alert alert-danger d-none"></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="content-card">
                <div class="content-card-body">
                    <form id="resignationForm" class="row g-4">
                        <div class="col-md-6">
                            <label for="proposed_last_working_date" class="form-label">Proposed Last Working Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="proposed_last_working_date" name="proposed_last_working_date" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="notice_period_days" class="form-label">Notice Period (days)</label>
                            <input type="number" class="form-control" id="notice_period_days" name="notice_period_days" min="0" max="365" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <label for="reason" class="form-label">Reason for Resignation <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="5" maxlength="2000" required placeholder="Briefly explain your reason for leaving"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="resignationSubmitBtn">Submit Resignation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="content-card">
                <div class="content-card-header border-bottom">
                    <h2 class="content-card-title mb-0">What happens next</h2>
                </div>
                <div class="content-card-body">
                    <ol class="small text-muted mb-0 ps-3">
                        <li class="mb-2">Manager/HR reviews your resignation</li>
                        <li class="mb-2">Department clearance checklist is completed</li>
                        <li class="mb-2">Assigned company assets are returned</li>
                        <li class="mb-2">You complete the exit survey</li>
                        <li>F&F settlement is processed before account closure</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    @vite(['resources/js/offboarding-apply.js'])
@endsection
