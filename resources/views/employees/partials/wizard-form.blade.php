<div class="employee-wizard">
    <div class="wizard-stepper" id="wizardStepper">
        <div class="wizard-progress-track">
            <div class="wizard-progress-fill" id="wizardProgressFill"></div>
        </div>
        <div class="wizard-steps">
            <div class="wizard-step" data-step="1">
                <div class="wizard-step-circle"><span>1</span></div>
                <span class="wizard-step-label">Personal</span>
                <span class="wizard-step-hint">Identity &amp; contact</span>
            </div>
            <div class="wizard-step" data-step="2">
                <div class="wizard-step-circle"><span>2</span></div>
                <span class="wizard-step-label">Employment</span>
                <span class="wizard-step-hint">Role &amp; probation</span>
            </div>
            <div class="wizard-step" data-step="3">
                <div class="wizard-step-circle"><span>3</span></div>
                <span class="wizard-step-label">Salary</span>
                <span class="wizard-step-hint">Compensation</span>
            </div>
            <div class="wizard-step" data-step="4">
                <div class="wizard-step-circle"><span>4</span></div>
                <span class="wizard-step-label">Review</span>
                <span class="wizard-step-hint">Confirm &amp; access</span>
            </div>
        </div>
    </div>

    {{-- Step 1: Personal --}}
    <div class="wizard-panel active" data-step-panel="1">
        <div class="wizard-panel-header wizard-panel-header--styled">
            <div class="wizard-panel-header-icon wizard-panel-header-icon--personal" aria-hidden="true">👤</div>
            <div>
                <span class="wizard-step-badge">Step 1 of 4</span>
                <h2 class="wizard-panel-title">Personal Information</h2>
                <p class="wizard-panel-desc">Basic identity and contact details for the employee record.</p>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">🪪</span>
                <div>
                    <h6 class="wizard-form-section-title">Basic Details</h6>
                    <p class="wizard-form-section-desc">Name, contact, and employee identification.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required placeholder="Enter first name">
                    <div class="invalid-feedback d-block" data-error="first_name"></div>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter last name">
                    <div class="invalid-feedback d-block" data-error="last_name"></div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Work Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required placeholder="name@company.com">
                    <div class="invalid-feedback d-block" data-error="email"></div>
                </div>
                <div class="col-md-6">
                    <label for="personal_email" class="form-label">Personal Email</label>
                    <input type="email" class="form-control" id="personal_email" name="personal_email" placeholder="personal@email.com">
                    <div class="form-text">Optional personal email for records (not used for portal login).</div>
                    <div class="invalid-feedback d-block" data-error="personal_email"></div>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="phone" name="phone" required inputmode="numeric" maxlength="10" placeholder="10 digit mobile number">
                    <div class="form-text">Digits only — used for login OTP and communication.</div>
                    <div class="invalid-feedback d-block" data-error="phone"></div>
                </div>
                <div class="col-md-6">
                    <label for="employee_code" class="form-label">Employee Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="employee_code" name="employee_code" required placeholder="e.g. EMP0001">
                    <div class="invalid-feedback d-block" data-error="employee_code"></div>
                </div>
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="gender"></div>
                </div>
                <div class="col-md-6">
                    <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                    <div class="form-text">Employee must be at least 18 years old.</div>
                    <div class="invalid-feedback d-block" data-error="date_of_birth"></div>
                </div>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">📍</span>
                <div>
                    <h6 class="wizard-form-section-title">Residential Address</h6>
                    <p class="wizard-form-section-desc">Current home address for records and correspondence.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-6">
                    <label for="address_line_1" class="form-label">Building / House No., Street</label>
                    <input type="text" class="form-control" id="address_line_1" name="address_line_1" placeholder="e.g. 42, MG Road">
                    <div class="invalid-feedback d-block" data-error="address_line_1"></div>
                </div>
                <div class="col-md-6">
                    <label for="address_line_2" class="form-label">Area / Landmark</label>
                    <input type="text" class="form-control" id="address_line_2" name="address_line_2" placeholder="e.g. Near City Mall">
                    <div class="invalid-feedback d-block" data-error="address_line_2"></div>
                </div>
                <div class="col-md-4">
                    <label for="city" class="form-label">City</label>
                    <input type="text" class="form-control" id="city" name="city" placeholder="City">
                    <div class="invalid-feedback d-block" data-error="city"></div>
                </div>
                <div class="col-md-4">
                    <label for="state" class="form-label">State</label>
                    <input type="text" class="form-control" id="state" name="state" placeholder="State">
                    <div class="invalid-feedback d-block" data-error="state"></div>
                </div>
                <div class="col-md-4">
                    <label for="postal_code" class="form-label">Pincode</label>
                    <input type="text" class="form-control" id="postal_code" name="postal_code" inputmode="numeric" maxlength="10" placeholder="e.g. 560001">
                    <div class="invalid-feedback d-block" data-error="postal_code"></div>
                </div>
                <div class="col-md-6">
                    <label for="country" class="form-label">Country</label>
                    <input type="text" class="form-control" id="country" name="country" value="India" placeholder="Country">
                    <div class="invalid-feedback d-block" data-error="country"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 2: Employment --}}
    <div class="wizard-panel" data-step-panel="2">
        <div class="wizard-panel-header wizard-panel-header--styled">
            <div class="wizard-panel-header-icon wizard-panel-header-icon--employment" aria-hidden="true">💼</div>
            <div>
                <span class="wizard-step-badge">Step 2 of 4</span>
                <h2 class="wizard-panel-title">Employment Details</h2>
                <p class="wizard-panel-desc">Role, department, reporting structure, and probation within your company.</p>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">🏢</span>
                <div>
                    <h6 class="wizard-form-section-title">Role &amp; Organization</h6>
                    <p class="wizard-form-section-desc">Department, designation, and reporting manager.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-6">
                    <label for="department_ids" class="form-label">Departments</label>
                    <select class="form-select employee-select2" id="department_ids" name="department_ids[]" multiple data-placeholder="Select departments">
                    </select>
                    <div class="form-text">You can assign the employee to one or more departments.</div>
                    <div class="invalid-feedback d-block" data-error="department_ids"></div>
                </div>
                <div class="col-md-6">
                    <label for="role_id" class="form-label">System Role <span class="text-danger">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">Select role</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="role_id"></div>
                </div>
                <div class="col-md-6">
                    <label for="designation" class="form-label">Designation</label>
                    <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g. Software Engineer">
                    <div class="invalid-feedback d-block" data-error="designation"></div>
                </div>
                <div class="col-md-6">
                    <label for="manager_id" class="form-label">Reporting Manager</label>
                    <select class="form-select" id="manager_id" name="manager_id">
                        <option value="">No manager</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="manager_id"></div>
                </div>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">📅</span>
                <div>
                    <h6 class="wizard-form-section-title">Employment Terms</h6>
                    <p class="wizard-form-section-desc">Joining date, employment type, and active status.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-4">
                    <label for="joining_date" class="form-label">Joining Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="joining_date" name="joining_date" required>
                    <div class="invalid-feedback d-block" data-error="joining_date"></div>
                </div>
                <div class="col-md-4">
                    <label for="employment_type" class="form-label">Employment Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="employment_type" name="employment_type" required>
                        <option value="full_time">Full Time</option>
                        <option value="part_time">Part Time</option>
                        <option value="contract">Contract</option>
                        <option value="intern">Intern</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="employment_type"></div>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="status"></div>
                </div>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">🕒</span>
                <div>
                    <h6 class="wizard-form-section-title">Shift Details</h6>
                    <p class="wizard-form-section-desc">Assign the employee's work shift and timings.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-6">
                    <label for="shift_id" class="form-label">Work Shift <span class="text-danger">*</span></label>
                    <select class="form-select" id="shift_id" name="shift_id" required>
                        <option value="">Select shift</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="shift_id"></div>
                </div>
                <div class="col-md-6">
                    <div id="shiftTimingPreview" class="shift-timing-preview d-none">
                        <span class="shift-timing-preview-label">Shift Timings</span>
                        <strong id="shiftTimingText">—</strong>
                        <span class="text-muted small" id="shiftBreakText"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="wizard-form-section wizard-form-section--accent">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">⏳</span>
                <div>
                    <h6 class="wizard-form-section-title">Probation Details</h6>
                    <p class="wizard-form-section-desc">Track probation period and confirmation status.</p>
                </div>
            </div>
            <div class="wizard-form-section-body">
                <div class="probation-toggle-bar">
                    <div class="form-check form-switch probation-applicable-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="probation_applicable" name="probation_applicable" value="1" checked>
                        <label class="form-check-label" for="probation_applicable">Probation Applicable</label>
                    </div>
                    <div class="invalid-feedback d-block" data-error="probation_applicable"></div>
                </div>
                <div class="row g-3 g-md-4 probation-fields-grid">
                    <div class="col-md-4 probation-field">
                        <label for="probation_period_months" class="form-label">Probation Period <span class="text-danger probation-required">*</span></label>
                        <select class="form-select" id="probation_period_months" name="probation_period_months">
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3" selected>3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="9">9 Months</option>
                            <option value="12">12 Months</option>
                        </select>
                        <div class="invalid-feedback d-block" data-error="probation_period_months"></div>
                    </div>
                    <div class="col-md-4 probation-field">
                        <label for="probation_end_date" class="form-label">Probation End Date <span class="text-danger probation-required">*</span></label>
                        <input type="date" class="form-control" id="probation_end_date" name="probation_end_date">
                        <div class="form-text">Auto-calculated from joining date and period.</div>
                        <div class="invalid-feedback d-block" data-error="probation_end_date"></div>
                    </div>
                    <div class="col-md-4 probation-field">
                        <label for="probation_status" class="form-label">Probation Status</label>
                        <select class="form-select" id="probation_status" name="probation_status">
                            <option value="on_probation">On Probation</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="extended">Extended</option>
                        </select>
                        <div class="invalid-feedback d-block" data-error="probation_status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 3: Salary --}}
    <div class="wizard-panel" data-step-panel="3">
        <div class="wizard-panel-header wizard-panel-header--styled">
            <div class="wizard-panel-header-icon wizard-panel-header-icon--salary" aria-hidden="true">💰</div>
            <div>
                <span class="wizard-step-badge">Step 3 of 4</span>
                <h2 class="wizard-panel-title">Salary &amp; Compensation</h2>
                <p class="wizard-panel-desc">Define the compensation structure for payroll. Bank details are added by the employee from their portal.</p>
            </div>
        </div>

        <div class="wizard-info-banner mb-4">
            <span class="wizard-info-banner-icon" aria-hidden="true">🏦</span>
            <div>
                <strong>Bank details not required here</strong>
                <p class="mb-0">Employees will add their bank account information after logging into the portal.</p>
            </div>
        </div>

        <div class="salary-summary-card mb-4" id="salarySummaryCard">
            <div class="salary-summary-item">
                <span class="salary-summary-label">Annual CTC</span>
                <span class="salary-summary-value" id="summaryAnnualCtc">₹ 0</span>
            </div>
            <div class="salary-summary-divider"></div>
            <div class="salary-summary-item">
                <span class="salary-summary-label">Monthly Gross</span>
                <span class="salary-summary-value" id="summaryMonthlyGross">₹ 0</span>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">📊</span>
                <div>
                    <h6 class="wizard-form-section-title">CTC Overview</h6>
                    <p class="wizard-form-section-desc">Annual cost to company and salary effective date.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-6">
                    <label for="annual_ctc" class="form-label">Annual CTC (₹) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control salary-input" id="annual_ctc" name="annual_ctc" min="1" step="0.01" required placeholder="600000">
                    <div class="invalid-feedback d-block" data-error="annual_ctc"></div>
                </div>
                <div class="col-md-6">
                    <label for="salary_effective_from" class="form-label">Effective From <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="salary_effective_from" name="salary_effective_from" required>
                    <div class="invalid-feedback d-block" data-error="salary_effective_from"></div>
                </div>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">🧾</span>
                <div>
                    <h6 class="wizard-form-section-title">Monthly Salary Components</h6>
                    <p class="wizard-form-section-desc">Break down monthly earnings into standard components.</p>
                </div>
            </div>
            <div class="wizard-form-section-body row g-3 g-md-4">
                <div class="col-md-4">
                    <label for="basic_salary" class="form-label">Basic Salary (₹) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control salary-input" id="basic_salary" name="basic_salary" min="1" step="0.01" required placeholder="25000">
                    <div class="invalid-feedback d-block" data-error="basic_salary"></div>
                </div>
                <div class="col-md-4">
                    <label for="hra_percent" class="form-label">HRA (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control salary-input" id="hra_percent" name="hra_percent" min="0" max="100" step="0.01" value="40">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">% of monthly CTC — Amount: <span id="hraAmountPreview">₹ 0</span></div>
                    <div class="invalid-feedback d-block" data-error="hra_percent"></div>
                </div>
                <div class="col-md-4">
                    <label for="special_allowance_percent" class="form-label">Special Allowance (%)</label>
                    <div class="input-group">
                        <input type="number" class="form-control salary-input" id="special_allowance_percent" name="special_allowance_percent" min="0" max="100" step="0.01" value="0">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">% of monthly CTC — Amount: <span id="specialAllowanceAmountPreview">₹ 0</span></div>
                    <div class="invalid-feedback d-block" data-error="special_allowance_percent"></div>
                </div>
                <div class="col-md-4">
                    <label for="conveyance_allowance" class="form-label">Conveyance (₹)</label>
                    <input type="number" class="form-control salary-input" id="conveyance_allowance" name="conveyance_allowance" min="0" step="0.01" value="0">
                    <div class="invalid-feedback d-block" data-error="conveyance_allowance"></div>
                </div>
                <div class="col-md-4">
                    <label for="medical_allowance" class="form-label">Medical (₹)</label>
                    <input type="number" class="form-control salary-input" id="medical_allowance" name="medical_allowance" min="0" step="0.01" value="0">
                    <div class="invalid-feedback d-block" data-error="medical_allowance"></div>
                </div>
                <div class="col-md-4">
                    <label for="other_allowance" class="form-label">Other Allowance (₹)</label>
                    <input type="number" class="form-control salary-input" id="other_allowance" name="other_allowance" min="0" step="0.01" value="0">
                    <div class="invalid-feedback d-block" data-error="other_allowance"></div>
                </div>
            </div>
        </div>

        <div class="wizard-form-section">
            <div class="wizard-form-section-head">
                <span class="wizard-form-section-icon" aria-hidden="true">⚖️</span>
                <div>
                    <h6 class="wizard-form-section-title">Statutory Compliance</h6>
                    <p class="wizard-form-section-desc">Enable applicable statutory deductions for payroll.</p>
                </div>
            </div>
            <div class="wizard-form-section-body">
                <div class="wizard-toggle-grid">
                    <label class="wizard-toggle-card" for="pf_applicable">
                        <input class="form-check-input" type="checkbox" id="pf_applicable" name="pf_applicable" value="1" checked>
                        <span class="wizard-toggle-card-body">
                            <strong>PF Applicable</strong>
                            <small>Provident Fund contribution</small>
                        </span>
                    </label>
                    <label class="wizard-toggle-card" for="esi_applicable">
                        <input class="form-check-input" type="checkbox" id="esi_applicable" name="esi_applicable" value="1">
                        <span class="wizard-toggle-card-body">
                            <strong>ESI Applicable</strong>
                            <small>Employee State Insurance</small>
                        </span>
                    </label>
                    <label class="wizard-toggle-card" for="professional_tax_applicable">
                        <input class="form-check-input" type="checkbox" id="professional_tax_applicable" name="professional_tax_applicable" value="1" checked>
                        <span class="wizard-toggle-card-body">
                            <strong>Professional Tax</strong>
                            <small>State professional tax deduction</small>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 4: Review --}}
    <div class="wizard-panel" data-step-panel="4">
        <div class="wizard-panel-header wizard-panel-header--styled">
            <div class="wizard-panel-header-icon wizard-panel-header-icon--review" aria-hidden="true">✅</div>
            <div>
                <span class="wizard-step-badge">Step 4 of 4</span>
                <h2 class="wizard-panel-title">Review &amp; Portal Access</h2>
                <p class="wizard-panel-desc">Confirm all details before saving the employee record.</p>
            </div>
        </div>

        <div class="portal-access-card mb-4" id="portalAccessSection">
            <div class="portal-access-card-icon">🔐</div>
            <div class="portal-access-card-body">
                <h6 class="mb-1">Give Portal Access</h6>
                <p class="text-muted small mb-0">Create a login account and email credentials to the employee.</p>
            </div>
            <div class="form-check form-switch portal-access-card-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="give_portal_access" name="give_portal_access" value="1">
            </div>
        </div>

        <div class="portal-access-card mb-4 d-none" id="portalAccessStatus">
            <div class="portal-access-card-icon">🔐</div>
            <div class="portal-access-card-body">
                <h6 class="mb-1">Portal Access</h6>
                <span class="badge text-bg-secondary" id="portalAccessBadge">No portal access</span>
                <p class="text-muted small mb-0 mt-2 d-none" id="resendWelcomeEmailHint">Send a new welcome email with a freshly generated password.</p>
            </div>
            <div class="form-check form-switch portal-access-card-switch d-none" id="grantPortalAccessWrap">
                <input class="form-check-input" type="checkbox" role="switch" id="grant_portal_access" name="grant_portal_access" value="1">
                <label class="form-check-label small" for="grant_portal_access">Grant now</label>
            </div>
            <div class="d-none" id="resendWelcomeEmailWrap">
                <button type="button" class="btn btn-outline-primary btn-sm" id="resendWelcomeEmailBtn">Resend welcome email</button>
            </div>
        </div>

        <div class="review-grid" id="reviewSummary"></div>
    </div>

    <div class="wizard-footer">
        <div class="wizard-footer-left">
            <a href="{{ route('web.employees.index') }}" class="btn btn-link text-muted text-decoration-none px-0">Cancel</a>
        </div>
        <div class="wizard-footer-actions">
            <button type="button" class="btn btn-outline-secondary d-none" id="wizardPrevBtn">
                ← Previous
            </button>
            <button type="button" class="btn btn-primary px-4" id="wizardNextBtn">
                Continue →
            </button>
            <button type="submit" class="btn btn-primary px-4 d-none" id="employeeSubmitBtn">
                Save Employee
            </button>
        </div>
    </div>
</div>
