<div class="profile-tab-section">
    <div class="profile-tab-section-head">
        <h3 class="profile-tab-section-title">Compliances</h3>
        <p class="profile-tab-section-desc">Submit each statutory ID separately for HR approval.</p>
    </div>

    <div id="profileCompliancesEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">🛡️</div>
        <p class="profile-tab-placeholder-title">No employee profile linked</p>
        <p class="profile-tab-placeholder-text">Compliance details can be added once your account is linked to an employee record.</p>
    </div>

    <div id="profileCompliancesContent" class="d-none">
        <div class="alert alert-info profile-document-policy mb-4" role="status">
            <strong>Submission policy:</strong>
            You can submit PAN, Aadhaar, UAN, PF, and ESI independently — each goes for HR review separately.
            While pending, a field cannot be changed. Once approved, you may submit changes and they will go for HR approval again.
            If rejected, you may re-submit that field.
            HR-submitted compliance details require Company Admin approval.
        </div>

        <div id="profileComplianceApprovalsSection" class="profile-info-card mb-4 d-none">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">Pending Compliance Field Reviews</h4>
                <span class="badge text-bg-warning" id="profilePendingComplianceCount">0 pending</span>
            </div>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Field</th>
                            <th>Value</th>
                            <th>Submitted By</th>
                            <th>Submitted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilePendingComplianceBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No pending compliance fields.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="profile-info-card mb-4">
            <h4 class="profile-info-card-title">Statutory Applicability (HR configured)</h4>
            <dl class="profile-dl" id="profileComplianceFlags"></dl>
        </div>

        <form id="profileComplianceFieldForm" class="profile-form profile-document-upload mb-4">
            <h4 class="profile-form-section-title">Submit Compliance Field</h4>
            <p class="text-muted small" id="profileComplianceFieldUploadHint">Select a field that is not yet submitted, or one that was rejected or approved for change.</p>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="profile_compliance_field_type" class="form-label">Field <span class="text-danger">*</span></label>
                    <select class="form-select" id="profile_compliance_field_type" name="field_type" required>
                        <option value="">Select field</option>
                        <option value="pan">PAN Number</option>
                        <option value="aadhaar">Aadhaar Number</option>
                        <option value="uan">UAN</option>
                        <option value="pf">PF Number</option>
                        <option value="esi">ESI Number</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="field_type"></div>
                </div>
                <div class="col-md-4">
                    <label for="profile_compliance_value" class="form-label">Value <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="profile_compliance_value" name="value" required>
                    <div class="invalid-feedback d-block" data-error="value"></div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 mt-3">
                <button type="submit" class="btn btn-primary" id="profileComplianceFieldSubmit">Submit for Approval</button>
                <span class="text-success small d-none" id="profileComplianceFieldStatus"></span>
            </div>
        </form>

        <div class="profile-info-card">
            <h4 class="profile-info-card-title">My Compliance Fields</h4>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Review Notes</th>
                            <th>Submitted</th>
                            <th>Reviewed By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profileComplianceFieldsTableBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No compliance fields submitted yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="profile-info-card mt-4">
            <h4 class="profile-info-card-title">Available Compliance Fields</h4>
            <div id="profileRequiredComplianceFields" class="profile-required-docs"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectProfileSubmissionModal" tabindex="-1" aria-labelledby="rejectProfileSubmissionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectProfileSubmissionModalLabel">Reject Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectProfileSubmissionForm">
                <div class="modal-body">
                    <p class="text-muted small">Provide a reason so the employee knows what to fix before re-submitting.</p>
                    <label for="rejectProfileSubmissionNotes" class="form-label">Rejection reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejectProfileSubmissionNotes" rows="4" required minlength="5" maxlength="1000" placeholder="Explain why this submission was rejected..."></textarea>
                    <div class="invalid-feedback d-block" data-error="notes"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="rejectProfileSubmissionSubmit">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
