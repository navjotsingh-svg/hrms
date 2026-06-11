<div class="profile-tab-section">
    <div class="profile-tab-section-head">
        <h3 class="profile-tab-section-title">Salary</h3>
        <p class="profile-tab-section-desc" id="profileSalaryTabDesc">View your current compensation structure as recorded by HR.</p>
    </div>

    <div id="profileSalaryEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">💰</div>
        <p class="profile-tab-placeholder-title">No salary details yet</p>
        <p class="profile-tab-placeholder-text">Salary information will appear here once HR configures your compensation.</p>
    </div>

    <div id="profileSalaryContent" class="d-none">
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
            <div class="profile-info-card mb-4">
                <h4 class="profile-info-card-title mb-1">Update / Revise Salary</h4>
                <p class="text-muted small mb-3">Saving creates a revision history entry with the previous salary snapshot.</p>
                <form id="profileSalaryForm" class="profile-form">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="profile_salary_annual_ctc" class="form-label">Annual CTC (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control profile-salary-input" id="profile_salary_annual_ctc" min="1" step="0.01" required>
                            <div class="invalid-feedback d-block" data-error="annual_ctc"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_effective_from" class="form-label">Effective From <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="profile_salary_effective_from" required>
                            <div class="invalid-feedback d-block" data-error="salary_effective_from"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_basic_salary" class="form-label">Basic Salary (₹) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control profile-salary-input" id="profile_salary_basic_salary" min="1" step="0.01" required>
                            <div class="invalid-feedback d-block" data-error="basic_salary"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_salary_hra_percent" class="form-label">HRA (%)</label>
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
                            <div class="invalid-feedback d-block" data-error="special_allowance_percent"></div>
                        </div>
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
                            <textarea class="form-control" id="profile_salary_revision_notes" rows="2" placeholder="Optional note for this salary revision (e.g. annual increment)"></textarea>
                            <div class="invalid-feedback d-block" data-error="revision_notes"></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <button type="submit" class="btn btn-primary" id="profileSalarySubmit">Save Salary Revision</button>
                        <span class="text-success small d-none" id="profileSalaryStatusMsg"></span>
                    </div>
                </form>
            </div>

            <div class="profile-info-card">
                <h4 class="profile-info-card-title">Revision History</h4>
                <div class="table-responsive">
                    <table class="table profile-documents-table mb-0">
                        <thead>
                            <tr>
                                <th>Revised On</th>
                                <th>Annual CTC</th>
                                <th>Monthly Gross</th>
                                <th>Effective From</th>
                                <th>Revised By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="profileSalaryRevisionsBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No previous revisions.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
