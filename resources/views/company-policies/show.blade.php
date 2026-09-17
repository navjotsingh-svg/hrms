@extends('layouts.app')

@section('title', 'Company Policy - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-title mb-1" id="companyPolicyShowTitle">Company Policy</h1>
            <p class="page-subtitle mb-0" id="companyPolicyShowSubtitle">Read the policy page carefully.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($canManage)
                <a href="{{ route('web.company-policies.edit', $policyId) }}" class="btn btn-outline-primary" id="companyPolicyEditLink">Edit</a>
            @endif
            <a href="{{ route('web.company-policies.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="companyPolicyShowAlert" class="alert d-none"></div>

    <div id="companyPolicyShowRoot"
         data-policy-id="{{ $policyId }}"
         data-can-manage="{{ $canManage ? '1' : '0' }}">
        <div class="content-card mb-4">
            <div class="content-card-body" id="companyPolicyMeta">
                <div class="text-muted py-4 text-center">Loading policy...</div>
            </div>
        </div>

        <div class="content-card mb-4">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">Policy Page</h2>
                <p class="small text-muted mb-0">View only. Copying and downloading this policy are not allowed.</p>
            </div>
            <div class="content-card-body company-policy-page" id="companyPolicyContent"></div>
        </div>

        <div class="content-card mb-4 d-none" id="companyPolicyConsentCard">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">Employee Consent</h2>
                <p class="small text-muted mb-0">Confirm with your personal email and signature that you have read and agree to this policy.</p>
            </div>
            <div class="content-card-body">
                <form id="companyPolicyConsentForm">
                    <div class="mb-3">
                        <label for="consentEmail" class="form-label">Personal email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="consentEmail" required maxlength="255" placeholder="Your personal email from profile">
                        <div class="form-text" id="consentEmailHint">Must match the personal email saved on your employee profile.</div>
                    </div>
                    <div class="mb-3">
                        <label for="consentSignatureName" class="form-label">Full name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="consentSignatureName" required maxlength="255" placeholder="Type your full legal name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Draw signature <span class="text-danger">*</span></label>
                        <div class="border rounded bg-white position-relative company-policy-signature-pad">
                            <canvas id="consentSignatureCanvas" width="600" height="180" class="w-100 d-block" style="touch-action: none; cursor: crosshair; height: 180px;"></canvas>
                        </div>
                        <button type="button" class="btn btn-link btn-sm px-0 mt-1" id="consentSignatureClearBtn">Clear drawing</button>
                    </div>
                    <button type="submit" class="btn btn-primary" id="consentSubmitBtn">Sign &amp; Give Consent</button>
                </form>
            </div>
        </div>

        <div class="content-card d-none" id="companyPolicyConsentedCard">
            <div class="content-card-body border-bottom">
                <h2 class="h6 mb-0">Your Consent Record</h2>
            </div>
            <div class="content-card-body" id="companyPolicyConsentedDetails"></div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/company-policies-show.js'])
@endpush
