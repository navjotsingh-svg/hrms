<div class="profile-tab-section">
    <div class="profile-tab-section-head d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <h3 class="profile-tab-section-title">Salary</h3>
            <p class="profile-tab-section-desc" id="profileSalaryTabDesc">View your current compensation structure as recorded by HR.</p>
        </div>
        <div id="profileSalaryActions" class="d-none d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" id="profileSalaryAddBtn">Add Salary</button>
            <button type="button" class="btn btn-primary btn-sm d-none" id="profileSalaryReviseBtn">Revise Salary</button>
        </div>
    </div>

    <div id="profileSalaryEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">💰</div>
        <p class="profile-tab-placeholder-title">No salary details yet</p>
        <p class="profile-tab-placeholder-text">Salary information will appear here once HR configures your compensation.</p>
    </div>

    <div id="profileSalaryContent" class="d-none">
        <ul class="nav nav-pills salary-inner-tabs mb-4" id="profileSalaryInnerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-salary-current-tab" data-bs-toggle="pill" data-bs-target="#profileSalaryCurrentPane" type="button" role="tab">Current Salary</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="profile-salary-timeline-tab" data-bs-toggle="pill" data-bs-target="#profileSalaryTimelinePane" type="button" role="tab">Timeline</button>
            </li>
        </ul>

        <div class="tab-content" id="profileSalaryInnerTabContent">
            <div class="tab-pane fade show active" id="profileSalaryCurrentPane" role="tabpanel">
        <div class="salary-summary-card mb-4" id="profileSalarySummaryCard">
            <div class="salary-summary-item">
                <span class="salary-summary-label">Annual CTC</span>
                <span class="salary-summary-value" id="profileSummaryAnnualCtc">₹ 0</span>
            </div>
            <div class="salary-summary-divider"></div>
            <div class="salary-summary-item">
                <span class="salary-summary-label">Monthly Gross</span>
                <span class="salary-summary-value" id="profileSummaryMonthlyGross">₹ 0</span>
            </div>
        </div>

        <div class="profile-info-card mb-4">
            <h4 class="profile-info-card-title">Current Compensation</h4>
            <dl class="profile-dl" id="profileSalaryDisplay"></dl>
        </div>

        <div id="profileSalaryManageSection" class="d-none">
            <div id="profileSalaryFormPanel" class="profile-info-card mb-4 d-none">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <h4 class="profile-info-card-title mb-1" id="profileSalaryFormTitle">Add Salary</h4>
                        <p class="text-muted small mb-0" id="profileSalaryFormDesc">Enter compensation details for this employee.</p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="profileSalaryFormCancel">Cancel</button>
                </div>

                <div id="profileSalaryCurrentBlock" class="salary-current-ctc-banner d-none mb-3">
                    <span class="text-muted">Current Annual CTC</span>
                    <strong id="profileSalaryCurrentCtc">—</strong>
                </div>

                <form id="profileSalaryForm" class="profile-form" novalidate>
                    <div id="profileSalaryAddCtcBlock" class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="profile_salary_annual_ctc" class="form-label">Annual CTC (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control profile-salary-input" id="profile_salary_annual_ctc" min="1" step="0.01">
                            <div class="invalid-feedback d-block" data-error="annual_ctc"></div>
                        </div>
                    </div>

                    <div id="profileSalaryReviseCtcBlock" class="d-none mb-3">
                        <label class="form-label d-block">Change in CTC</label>
                        <div class="salary-ctc-mode-toggle btn-group mb-3" role="group" aria-label="CTC change mode">
                            <input type="radio" class="btn-check" name="profile_ctc_mode" id="profile_ctc_mode_percent" value="percent" checked>
                            <label class="btn btn-outline-primary btn-sm" for="profile_ctc_mode_percent">Increase %</label>
                            <input type="radio" class="btn-check" name="profile_ctc_mode" id="profile_ctc_mode_revised" value="revised">
                            <label class="btn btn-outline-primary btn-sm" for="profile_ctc_mode_revised">Revised CTC</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6" id="profileCtcPercentWrap">
                                <label for="profile_salary_increase_percent" class="form-label">Increase %</label>
                                <div class="input-group">
                                    <input type="number" class="form-control profile-salary-input" id="profile_salary_increase_percent" min="0" max="500" step="0.01" placeholder="e.g. 10">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">New CTC: <strong id="profileRevisedCtcPreview">—</strong></div>
                            </div>
                            <div class="col-md-6 d-none" id="profileCtcRevisedWrap">
                                <label for="profile_salary_revised_ctc" class="form-label">Revised CTC (₹) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control profile-salary-input" id="profile_salary_revised_ctc" min="1" step="0.01">
                                <div class="invalid-feedback d-block" data-error="revised_ctc"></div>
                            </div>
                        </div>
                        <input type="hidden" id="profile_salary_computed_ctc">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="profile_salary_effective_from" class="form-label">Effective Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="profile_salary_effective_from">
                            <div class="form-text">Date from which the revised CTC comes into effect.</div>
                            <div class="invalid-feedback d-block" data-error="salary_effective_from"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_payout_from" class="form-label">Payout From <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="profile_salary_payout_from">
                            <div class="form-text">Date from which the new salary is paid in payroll.</div>
                            <div class="invalid-feedback d-block" data-error="salary_payout_from"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_basic_percent" class="form-label">Basic % <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control profile-salary-input" id="profile_salary_basic_percent" min="1" max="100" step="0.01">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">% of monthly CTC — <span id="profileBasicAmountPreview">₹ 0</span></div>
                            <input type="hidden" id="profile_salary_basic_salary">
                            <div class="invalid-feedback d-block" data-error="basic_salary"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_hra_percent" class="form-label">HRA %</label>
                            <div class="input-group">
                                <input type="number" class="form-control profile-salary-input" id="profile_salary_hra_percent" min="0" max="100" step="0.01" value="40">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">% of monthly CTC — <span id="profileHraAmountPreview">₹ 0</span></div>
                            <div class="invalid-feedback d-block" data-error="hra_percent"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_special_allowance_percent" class="form-label">Special Allowance (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control profile-salary-input" id="profile_salary_special_allowance_percent" min="0" max="100" step="0.01" value="0">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">% of monthly CTC — <span id="profileSpecialAllowanceAmountPreview">₹ 0</span></div>
                        </div>
                    </div>

                    <div class="mt-4 mb-2">
                        <h6 class="mb-1">Optional Salary Components</h6>
                        <p class="text-muted small mb-3">Fixed monthly allowances added to the salary structure.</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="profile_salary_conveyance_allowance" class="form-label">Conveyance (₹)</label>
                            <input type="number" class="form-control profile-salary-input" id="profile_salary_conveyance_allowance" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_salary_medical_allowance" class="form-label">Medical (₹)</label>
                            <input type="number" class="form-control profile-salary-input" id="profile_salary_medical_allowance" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_salary_other_allowance" class="form-label">Other Allowance (₹)</label>
                            <input type="number" class="form-control profile-salary-input" id="profile_salary_other_allowance" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="profile_salary_pf_applicable" checked>
                                    <label class="form-check-label" for="profile_salary_pf_applicable">PF Applicable</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="profile_salary_esi_applicable">
                                    <label class="form-check-label" for="profile_salary_esi_applicable">ESI Applicable</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="profile_salary_professional_tax_applicable" checked>
                                    <label class="form-check-label" for="profile_salary_professional_tax_applicable">Professional Tax</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="profile_salary_revision_notes" class="form-label">Revision Notes</label>
                            <textarea class="form-control" id="profile_salary_revision_notes" rows="2" placeholder="Optional note (e.g. annual increment, promotion)"></textarea>
                            <div class="invalid-feedback d-block" data-error="revision_notes"></div>
                        </div>
                    </div>

                    <div class="salary-review-card mt-4" id="profileSalaryReviewCard">
                        <h6 class="salary-review-card-title mb-3">Confirm Salary Details</h6>
                        <dl class="profile-dl profile-dl-compact mb-0" id="profileSalaryReviewSummary"></dl>
                    </div>

                    <div class="d-flex align-items-center gap-3 mt-3">
                        <button type="submit" class="btn btn-primary" id="profileSalarySubmit">Submit</button>
                        <span class="text-success small d-none" id="profileSalaryStatusMsg"></span>
                    </div>
                </form>
            </div>
        </div>
            </div>

            <div class="tab-pane fade" id="profileSalaryTimelinePane" role="tabpanel">
                <div id="profileSalaryTimelineSection" class="profile-info-card">
                    <h4 class="profile-info-card-title mb-1">Salary Timeline</h4>
                    <p class="text-muted small mb-4">Track each compensation period — what applied from when to when, what changed, and when it was updated.</p>
                    <div id="profileSalaryTimelineContainer"></div>
                </div>
            </div>
        </div>
    </div>
</div>
