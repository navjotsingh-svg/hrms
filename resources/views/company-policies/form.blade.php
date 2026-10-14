@extends('layouts.app')

@php
    $mode = $mode ?? 'create';
    $isEdit = $mode === 'edit';
@endphp

@section('title', ($isEdit ? 'Edit Policy' : 'Add Policy') . ' - ' . config('app.name', 'HRMS'))

@section('header')
    <div class="hrms-page-banner__heading">
        <div class="hrms-page-banner__copy">
            <h1 class="page-title mb-1">{{ $isEdit ? 'Edit Policy Page' : 'Add Policy Page' }}</h1>
            <p class="page-subtitle mb-0">Write the policy in the editor. Employees will read it as a page and can give consent.</p>
        </div>
        <div class="hrms-page-banner__actions">
            <a href="{{ route('web.company-policies.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>
@endsection

@section('content')
    <div id="companyPolicyFormAlert" class="alert d-none"></div>

    <div class="content-card"
         id="companyPolicyFormRoot"
         data-mode="{{ $mode }}"
         data-policy-id="{{ $policyId ?? '' }}">
        <form id="companyPolicyEditorForm">
            <div class="content-card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="companyPolicyTitle" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="companyPolicyTitle" required maxlength="255" placeholder="e.g. Code of Conduct">
                    </div>
                    <div class="col-md-4">
                        <label for="companyPolicyCategory" class="form-label">Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="companyPolicyCategory" required></select>
                    </div>
                    <div class="col-12">
                        <label for="companyPolicyDescription" class="form-label">Short summary</label>
                        <textarea class="form-control" id="companyPolicyDescription" rows="2" maxlength="2000" placeholder="Optional summary shown in the list"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label for="companyPolicyStatus" class="form-label">Status</label>
                        <select class="form-select" id="companyPolicyStatus">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="companyPolicyRequiresConsent" checked>
                            <label class="form-check-label" for="companyPolicyRequiresConsent">
                                Require employee signature consent with personal email
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <label class="form-label mb-0">Policy page content <span class="text-danger">*</span></label>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Policy editor mode">
                                <button type="button" class="btn btn-outline-secondary active" id="companyPolicyVisualTab">Visual</button>
                                <button type="button" class="btn btn-outline-secondary" id="companyPolicyHtmlTab">HTML &amp; CSS</button>
                            </div>
                        </div>
                        <div class="company-policy-editor" id="companyPolicyVisualPane">
                            <div id="companyPolicyBodyEditor"></div>
                        </div>
                        <textarea id="companyPolicyBodyHtml" class="form-control company-policy-html-source d-none" rows="18" spellcheck="false" placeholder="Write HTML and CSS. Example:&#10;&lt;style&gt;&#10;h1 { color: #1e3a5f; }&#10;p { line-height: 1.7; }&#10;&lt;/style&gt;&#10;&lt;h1&gt;Code of Conduct&lt;/h1&gt;&#10;&lt;p&gt;Policy text&lt;/p&gt;"></textarea>
                        <div class="form-text">Use HTML &amp; CSS for the layout employees see on the policy page. Styles stay inside that preview.</div>
                    </div>
                </div>
            </div>
            <div class="content-card-footer d-flex flex-wrap justify-content-between gap-2">
                <a href="{{ route('web.company-policies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="companyPolicySaveBtn">Save Policy</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/company-policies-form.js'])
@endpush
