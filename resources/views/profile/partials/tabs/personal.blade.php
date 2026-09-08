<div class="profile-tab-section">
    <div class="profile-tab-section-head">
        <h3 class="profile-tab-section-title">Personal</h3>
        <p class="profile-tab-section-desc">View your identity details and submit family, address, and emergency contact information for HR approval.</p>
    </div>

    <div id="profilePersonalEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">👤</div>
        <p class="profile-tab-placeholder-title">No employee profile linked</p>
        <p class="profile-tab-placeholder-text">Personal employee details are available once your account is linked to an employee record.</p>
    </div>

    <div id="profilePersonalContent" class="d-none">
        <div id="profileSubmissionPolicyAlert" class="alert alert-info profile-document-policy mb-4" role="status">
            <strong>Submission policy:</strong>
            Family details, address, and emergency contact are submitted separately for HR review.
            Use the Add or Change actions on each section to submit updates for approval.
            Mobile number and work email can only be changed by HR or Company Admin.
            Emergency contact details are entered separately and do not need to match a family member above.
        </div>

        <div id="profilePersonalApprovalsSection" class="profile-info-card mb-4 d-none">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">Pending Personal Section Reviews</h4>
                <span class="badge text-bg-warning" id="profilePendingPersonalCount">0 pending</span>
            </div>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Section</th>
                            <th>Summary</th>
                            <th>Submitted By</th>
                            <th>Submitted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilePendingPersonalBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No pending personal sections.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="profile-info-card mb-4">
            <h4 class="profile-info-card-title">1. Display Information</h4>
            <p class="text-muted small mb-3">These details are managed by HR. Contact HR or Company Admin to update mobile number or work email.</p>
            <dl class="profile-dl" id="profilePersonalDisplayInfo"></dl>
        </div>

        <div class="profile-info-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">2. Family Details</h4>
                <button type="button" class="table-action-btn table-action-btn--edit d-none" id="profileFamilySectionEditBtn" title="Edit family details" aria-label="Edit family details">
                    @include('partials.icons.edit')
                </button>
            </div>
            <p class="text-muted small mt-0 mb-3">Each family relation is submitted and reviewed separately.</p>

            <div id="profileFamilyApprovalsSection" class="profile-info-card mb-4 d-none">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h5 class="profile-info-card-title mb-0">Pending Family Member Reviews</h5>
                    <span class="badge text-bg-warning" id="profilePendingFamilyCount">0 pending</span>
                </div>
                <div class="table-responsive">
                    <table class="table profile-documents-table mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Name</th>
                                <th>Relation</th>
                                <th>Submitted By</th>
                                <th>Submitted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="profilePendingFamilyBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No pending family members.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="profile-info-card mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h5 class="profile-info-card-title mb-0">My Family Members</h5>
                    <button type="button" class="btn btn-sm btn-primary d-none" id="profileAddFamilyMemberBtn">+ Add Family Member</button>
                </div>
                <div class="table-responsive">
                    <table class="table profile-documents-table mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Relation</th>
                                <th>Mobile</th>
                                <th>Date of Birth</th>
                                <th>Status</th>
                                <th>Review Notes</th>
                                <th>Submitted</th>
                                <th>Reviewed By</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="profileFamilyMembersTableBody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No family members submitted yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <form id="profileFamilySectionForm" class="profile-form d-none">
                <input type="hidden" id="profileFamilyResubmitId" value="">
                <p class="text-muted small" id="profileFamilySectionHint">Add new family members below. Submitted relations appear in the listing above only.</p>
                <div id="profileFamilyMembersList" class="d-flex flex-column gap-3 mb-3"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="profileAddFamilyMember">+ Add Family Member</button>
                <div class="d-flex align-items-center gap-3">
                    <button type="submit" class="btn btn-primary" id="profileFamilySectionSubmit">Submit for Approval</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="profileFamilyResubmitCancel">Cancel</button>
                    <span class="text-success small d-none" id="profileFamilySectionStatusMsg"></span>
                </div>
            </form>
        </div>

        <div class="profile-info-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">3. Address</h4>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span id="profileAddressSectionStatus"></span>
                    <button type="button" class="table-action-btn table-action-btn--edit d-none" id="profileAddressEditBtn" title="Edit address" aria-label="Edit address">
                        @include('partials.icons.edit')
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="profileAddressAddBtn">Add Address</button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="profileAddressChangeBtn">Change</button>
                </div>
            </div>
            <div id="profileAddressSectionNotes" class="mb-3"></div>
            <div id="profileAddressApprovedView" class="mb-3"></div>
            <form id="profileAddressSectionForm" class="profile-form d-none">
                <div class="profile-form-section">
                    <h5 class="profile-form-section-title">Permanent Address</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="profile_permanent_address_line_1" class="form-label">Building / Street <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="profile_permanent_address_line_1" name="permanent_address_line_1" required>
                            <div class="invalid-feedback d-block" data-error="permanent.address_line_1"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="profile_permanent_address_line_2" class="form-label">Area / Landmark</label>
                            <input type="text" class="form-control" id="profile_permanent_address_line_2" name="permanent_address_line_2">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_permanent_city" class="form-label">City</label>
                            <input type="text" class="form-control" id="profile_permanent_city" name="permanent_city">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_permanent_state" class="form-label">State</label>
                            <input type="text" class="form-control" id="profile_permanent_state" name="permanent_state">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_permanent_postal_code" class="form-label">Pincode</label>
                            <input type="text" class="form-control" id="profile_permanent_postal_code" name="permanent_postal_code" inputmode="numeric">
                        </div>
                        <div class="col-md-6">
                            <label for="profile_permanent_country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="profile_permanent_country" name="permanent_country" value="India">
                        </div>
                    </div>
                </div>

                <div class="profile-form-section mt-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h5 class="profile-form-section-title mb-0">Current Address</h5>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="profile_same_as_permanent" name="same_as_permanent">
                            <label class="form-check-label" for="profile_same_as_permanent">Same as permanent address</label>
                        </div>
                    </div>
                    <div id="profileTemporaryAddressFields" class="row g-3">
                        <div class="col-md-6">
                            <label for="profile_temp_address_line_1" class="form-label">Building / Street</label>
                            <input type="text" class="form-control" id="profile_temp_address_line_1" name="temp_address_line_1">
                        </div>
                        <div class="col-md-6">
                            <label for="profile_temp_address_line_2" class="form-label">Area / Landmark</label>
                            <input type="text" class="form-control" id="profile_temp_address_line_2" name="temp_address_line_2">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_temp_city" class="form-label">City</label>
                            <input type="text" class="form-control" id="profile_temp_city" name="temp_city">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_temp_state" class="form-label">State</label>
                            <input type="text" class="form-control" id="profile_temp_state" name="temp_state">
                        </div>
                        <div class="col-md-4">
                            <label for="profile_temp_postal_code" class="form-label">Pincode</label>
                            <input type="text" class="form-control" id="profile_temp_postal_code" name="temp_postal_code" inputmode="numeric">
                        </div>
                        <div class="col-md-6">
                            <label for="profile_temp_country" class="form-label">Country</label>
                            <input type="text" class="form-control" id="profile_temp_country" name="temp_country" value="India">
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mt-3">
                    <button type="submit" class="btn btn-primary" id="profileAddressSectionSubmit">Submit for Approval</button>
                    <span class="text-success small d-none" id="profileAddressSectionStatusMsg"></span>
                </div>
            </form>
        </div>

        <div class="profile-info-card mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">4. Emergency Contact</h4>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span id="profileEmergencySectionStatus"></span>
                    <button type="button" class="table-action-btn table-action-btn--edit d-none" id="profileEmergencyEditBtn" title="Edit emergency contacts" aria-label="Edit emergency contacts">
                        @include('partials.icons.edit')
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="profileEmergencyAddBtn">Add Contact</button>
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="profileEmergencyChangeBtn">Change</button>
                </div>
            </div>
            <div id="profileEmergencySectionNotes" class="mb-3"></div>
            <div id="profileEmergencyApprovedView" class="mb-3"></div>
            <form id="profileEmergencySectionForm" class="profile-form d-none">
                <p class="text-muted small" id="profileEmergencySectionHint">Add one or more emergency contacts. Each contact can have multiple mobile numbers.</p>
                <div id="profileEmergencyContactsList" class="d-flex flex-column gap-3 mb-3"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="profileAddEmergencyContact">+ Add Emergency Contact</button>
                <div class="d-flex align-items-center gap-3 mt-3">
                    <button type="submit" class="btn btn-primary" id="profileEmergencySectionSubmit">Submit for Approval</button>
                    <span class="text-success small d-none" id="profileEmergencySectionStatusMsg"></span>
                </div>
            </form>
        </div>
    </div>

    @unless ($hideAccountSettings ?? false)
    <div class="profile-tab-divider"></div>

    <div class="profile-tab-subsection">
        <div class="profile-tab-section-head">
            <h4 class="profile-tab-subsection-title">Account Information</h4>
            <p class="profile-tab-section-desc mb-0">Manage your login name. Work email changes require HR or Company Admin.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>
    @endunless
</div>

<template id="profileFamilyMemberRowTemplate">
    <div class="profile-family-member-row border rounded p-3" data-family-member-row>
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <strong class="profile-family-member-row-title">Family Member</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-family-member>&times; Remove</button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-family-name required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Relation <span class="text-danger">*</span></label>
                @include('profile.partials.relation-select', ['dataAttribute' => 'data-family-relation'])
            </div>
            <div class="col-md-4">
                <label class="form-label">Mobile</label>
                <input type="tel" class="form-control" data-family-phone inputmode="numeric" maxlength="10" pattern="[0-9]{10}" title="Enter a 10-digit mobile number">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date of Birth</label>
                <input type="date" class="form-control" data-family-dob min="1900-01-01">
            </div>
        </div>
    </div>
</template>

<template id="profileEmergencyContactRowTemplate">
    <div class="profile-emergency-contact-row border rounded p-3" data-emergency-contact-row>
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <strong class="profile-emergency-contact-row-title">Emergency Contact</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-emergency-contact>&times; Remove</button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" data-emergency-name required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Relation <span class="text-danger">*</span></label>
                @include('profile.partials.relation-select', ['dataAttribute' => 'data-emergency-relation'])
            </div>
        </div>
        <div class="mt-3">
            <label class="form-label">Mobile Numbers <span class="text-danger">*</span></label>
            <div class="d-flex flex-column gap-2" data-emergency-phones-list></div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-add-emergency-phone>+ Add Phone Number</button>
        </div>
    </div>
</template>

<template id="profileEmergencyPhoneRowTemplate">
    <div class="input-group" data-emergency-phone-row>
        <input type="tel" class="form-control" data-emergency-phone inputmode="numeric" maxlength="10" pattern="[0-9]{10}" title="Enter a 10-digit mobile number" required>
        <button type="button" class="btn btn-outline-danger" data-remove-emergency-phone aria-label="Remove phone">&times;</button>
    </div>
</template>
