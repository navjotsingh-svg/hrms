<div class="profile-tab-section">
    <div class="profile-tab-section-head">
        <h3 class="profile-tab-section-title">Bank</h3>
        <p class="profile-tab-section-desc">Submit each salary payment option separately for HR approval.</p>
    </div>

    <div id="profileBankEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">🏦</div>
        <p class="profile-tab-placeholder-title">No employee profile linked</p>
        <p class="profile-tab-placeholder-text">Payment options can be added once your account is linked to an employee record.</p>
    </div>

    <div id="profileBankContent" class="d-none">
        <div class="alert alert-info profile-document-policy mb-4" role="status">
            <strong>Submission policy:</strong>
            You can submit Bank Transfer, Cash, and Cheque as separate options — each goes for HR review independently.
            While pending, an option cannot be changed. Once approved, you may submit changes and they will go for HR approval again.
            If rejected, you may re-submit that option.
        </div>

        <div id="profileBankApprovalsSection" class="profile-info-card mb-4 d-none">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">Pending Payment Option Reviews</h4>
                <span class="badge text-bg-warning" id="profilePendingBankCount">0 pending</span>
            </div>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Payment Option</th>
                            <th>Details</th>
                            <th>Submitted By</th>
                            <th>Submitted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilePendingBankBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No pending payment options.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <form id="profilePaymentMethodForm" class="profile-form profile-document-upload mb-4">
            <h4 class="profile-form-section-title">Submit Payment Option</h4>
            <p class="text-muted small" id="profilePaymentMethodUploadHint">Select a payment option that is not yet submitted, or one that was rejected.</p>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="profile_payment_mode" class="form-label">Payment Option <span class="text-danger">*</span></label>
                    <select class="form-select" id="profile_payment_mode" name="payment_mode" required>
                        <option value="">Select payment option</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="payment_mode"></div>
                </div>
            </div>

            <div id="profileBankNonTransferNote" class="alert alert-secondary mt-3 d-none" role="status">
                No bank account is required for Cash or Cheque. Submit for Approval to send this option to HR.
            </div>

            <div id="profileBankFields" class="profile-form-section mt-3 d-none">
                <h4 class="profile-form-section-title">Bank Account</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="profile_bank_name" class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_bank_name" name="bank_name" placeholder="e.g. State Bank of India">
                        <div class="invalid-feedback d-block" data-error="bank_name"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_bank_branch" class="form-label">Branch <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_bank_branch" name="bank_branch" placeholder="e.g. Andheri West">
                        <div class="invalid-feedback d-block" data-error="bank_branch"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_bank_address" class="form-label">Bank Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_bank_address" name="bank_address" placeholder="Branch address">
                        <div class="invalid-feedback d-block" data-error="bank_address"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_account_holder_name" class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_account_holder_name" name="account_holder_name">
                        <div class="invalid-feedback d-block" data-error="account_holder_name"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="profile_account_number" name="account_number" inputmode="numeric">
                        <div class="invalid-feedback d-block" data-error="account_number"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="profile_ifsc_code" class="form-label">IFSC Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control text-uppercase" id="profile_ifsc_code" name="ifsc_code" maxlength="11" placeholder="e.g. SBIN0001234">
                        <div class="invalid-feedback d-block" data-error="ifsc_code"></div>
                    </div>
                </div>

                <div id="profileBankProofFields" class="profile-form-section mt-3 d-none">
                    <h4 class="profile-form-section-title">Bank Proof</h4>
                    <p class="text-muted small mb-2">Upload cancelled cheque, passbook photo, or bank statement (PDF/JPG/PNG). You can attach multiple files.</p>
                    <div class="col-md-8">
                        <label for="profile_bank_proofs" class="form-label">Proof Attachments <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="profile_bank_proofs" name="proofs[]" multiple accept=".pdf,.jpg,.jpeg,.png,image/*,application/pdf">
                        <div class="invalid-feedback d-block" data-error="proofs"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 mt-3">
                <button type="submit" class="btn btn-primary" id="profilePaymentMethodSubmit">Submit for Approval</button>
                <span class="text-success small d-none" id="profilePaymentMethodStatus"></span>
            </div>
        </form>

        <div class="profile-info-card">
            <h4 class="profile-info-card-title">My Payment Options</h4>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Payment Option</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Review Notes</th>
                            <th>Submitted</th>
                            <th>Reviewed By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilePaymentMethodsTableBody">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No payment options submitted yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="profile-info-card mt-4">
            <h4 class="profile-info-card-title">Available Payment Options</h4>
            <div id="profileRequiredPaymentMethods" class="profile-required-docs"></div>
        </div>
    </div>
</div>
