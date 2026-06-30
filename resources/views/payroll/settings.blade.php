@extends('layouts.app')



@section('title', 'Payroll Settings - ' . config('app.name', 'HRMS'))



@section('header')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

        <div>

            <h1 class="page-title mb-1">Payroll Settings</h1>

            <p class="page-subtitle mb-0">Configure salary structure and statutory compliance defaults for all employees.</p>

        </div>

        <a href="{{ route('web.payroll.index') }}" class="btn btn-outline-secondary">Back to Payroll</a>

    </div>

@endsection



@section('content')

    <div id="payrollSettingsAlert" class="alert alert-success alert-dismissible fade show d-none" role="alert"></div>



    <div class="content-card">

        <div class="content-card-body">

            <form id="payrollSettingsForm">

                <div class="wizard-info-banner mb-4">

                    <span class="wizard-info-banner-icon" aria-hidden="true">🏢</span>

                    <div>

                        <strong>Company-wide payroll defaults</strong>

                        <p class="mb-0">These apply to every employee salary record. Updating here recalculates all existing employee salaries automatically.</p>

                    </div>

                </div>



                <div class="wizard-form-section">

                    <div class="wizard-form-section-head">

                        <span class="wizard-form-section-icon" aria-hidden="true">💰</span>

                        <div>

                            <h6 class="wizard-form-section-title">Salary Structure</h6>

                            <p class="wizard-form-section-desc">Default monthly salary components applied when assigning or revising employee CTC.</p>

                        </div>

                    </div>

                    <div class="wizard-form-section-body row g-3 g-md-4">

                        <div class="col-md-4">

                            <label for="company_basic_salary_percent" class="form-label">Basic Salary (%) <span class="text-danger">*</span></label>

                            <div class="input-group">

                                <input type="number" class="form-control payroll-settings-input" id="company_basic_salary_percent" name="basic_salary_percent" min="1" max="100" step="0.01" value="50" required>

                                <span class="input-group-text">%</span>

                            </div>

                            <div class="form-text">% of monthly CTC — Amount: <span id="payrollSettingsBasicPreview">₹ 0</span></div>

                        </div>

                        <div class="col-md-4">

                            <label for="company_hra_percent" class="form-label">HRA (%) <span class="text-danger">*</span></label>

                            <div class="input-group">

                                <input type="number" class="form-control payroll-settings-input" id="company_hra_percent" name="hra_percent" min="0" max="100" step="0.01" value="40" required>

                                <span class="input-group-text">%</span>

                            </div>

                            <div class="form-text">% of monthly CTC — Amount: <span id="payrollSettingsHraPreview">₹ 0</span></div>

                        </div>

                        <div class="col-md-4">

                            <label for="company_special_allowance_percent" class="form-label">Special Allowance (%) <span class="text-danger">*</span></label>

                            <div class="input-group">

                                <input type="number" class="form-control payroll-settings-input" id="company_special_allowance_percent" name="special_allowance_percent" min="0" max="100" step="0.01" value="0" required>

                                <span class="input-group-text">%</span>

                            </div>

                            <div class="form-text">% of monthly CTC — Amount: <span id="payrollSettingsSpecialPreview">₹ 0</span></div>

                        </div>

                        <div class="col-md-4">

                            <label for="company_conveyance_allowance" class="form-label">Conveyance (₹)</label>

                            <input type="number" class="form-control payroll-settings-input" id="company_conveyance_allowance" name="conveyance_allowance" min="0" step="0.01" value="0">

                        </div>

                        <div class="col-md-4">

                            <label for="company_medical_allowance" class="form-label">Medical (₹)</label>

                            <input type="number" class="form-control payroll-settings-input" id="company_medical_allowance" name="medical_allowance" min="0" step="0.01" value="0">

                        </div>

                        <div class="col-md-4">

                            <label for="company_other_allowance" class="form-label">Other Allowance (₹)</label>

                            <input type="number" class="form-control payroll-settings-input" id="company_other_allowance" name="other_allowance" min="0" step="0.01" value="0">

                        </div>

                        <div class="col-12">

                            <label for="payrollSettingsSampleCtc" class="form-label">Preview sample (Annual CTC)</label>

                            <div class="row g-3 align-items-end">

                                <div class="col-md-4">

                                    <input type="number" class="form-control payroll-settings-input" id="payrollSettingsSampleCtc" min="1" step="0.01" value="180000" placeholder="180000">

                                </div>

                                <div class="col-md-8">

                                    <p class="text-muted small mb-0">Monthly gross preview: <strong id="payrollSettingsMonthlyGrossPreview">₹ 0</strong></p>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <div class="wizard-form-section mt-4">

                    <div class="wizard-form-section-head">

                        <span class="wizard-form-section-icon" aria-hidden="true">🧾</span>

                        <div>

                            <h6 class="wizard-form-section-title">Statutory Compliance</h6>

                            <p class="wizard-form-section-desc">Enable applicable statutory deductions for payroll across the organization.</p>

                        </div>

                    </div>

                    <div class="wizard-form-section-body">

                        <div class="wizard-toggle-grid">

                            <label class="wizard-toggle-card" for="company_pf_applicable">

                                <input class="form-check-input" type="checkbox" id="company_pf_applicable" name="pf_applicable" value="1" checked>

                                <span class="wizard-toggle-card-body">

                                    <strong>PF Applicable</strong>

                                    <small>Provident Fund contribution</small>

                                </span>

                            </label>

                            <label class="wizard-toggle-card" for="company_esi_applicable">

                                <input class="form-check-input" type="checkbox" id="company_esi_applicable" name="esi_applicable" value="1">

                                <span class="wizard-toggle-card-body">

                                    <strong>ESI Applicable</strong>

                                    <small>Employee State Insurance</small>

                                </span>

                            </label>

                            <label class="wizard-toggle-card" for="company_professional_tax_applicable">

                                <input class="form-check-input" type="checkbox" id="company_professional_tax_applicable" name="professional_tax_applicable" value="1" checked>

                                <span class="wizard-toggle-card-body">

                                    <strong>Professional Tax</strong>

                                    <small>State professional tax deduction</small>

                                </span>

                            </label>

                        </div>

                    </div>

                </div>



                <div class="mt-4 d-flex gap-2">

                    <button type="submit" class="btn btn-primary" id="payrollSettingsSubmitBtn">Save Settings</button>

                </div>

            </form>

        </div>

    </div>

    @vite(['resources/js/payroll.js'])

@endsection

