<div class="profile-tab-section">
    <div class="profile-tab-section-head">
        <h3 class="profile-tab-section-title">Documents</h3>
        <p class="profile-tab-section-desc">Upload required documents for verification. Document types can allow a single file or multiple files, depending on how HR configured each type.</p>
    </div>

    <div id="profileDocumentsEmpty" class="profile-tab-placeholder d-none">
        <div class="profile-tab-placeholder-icon" aria-hidden="true">📁</div>
        <p class="profile-tab-placeholder-title">No employee profile linked</p>
        <p class="profile-tab-placeholder-text">Document uploads are available once your account is linked to an employee record.</p>
    </div>

    <div id="profileDocumentsContent" class="d-none">
        <div class="alert alert-info profile-document-policy mb-4" role="status">
            <strong>Upload policy:</strong>
            Single-file types allow one upload per employee (re-upload only if rejected).
            Multiple-file types let you upload several files for the same document type.
            HR-uploaded documents require Company Admin approval; all other uploads require HR or Admin approval.
        </div>

        <div id="profileDocumentApprovalsSection" class="profile-info-card mb-4 d-none">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h4 class="profile-info-card-title mb-0">Pending Document Reviews</h4>
                <span class="badge text-bg-warning" id="profilePendingDocumentsCount">0 pending</span>
            </div>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Document</th>
                            <th>Uploaded By</th>
                            <th>Uploaded</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profilePendingDocumentsBody">
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No pending documents.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <form id="profileDocumentForm" class="profile-form profile-document-upload mb-4" enctype="multipart/form-data">
            <h4 class="profile-form-section-title">Upload Document</h4>
            <p class="text-muted small" id="profileDocumentUploadHint">Select a document type that is not yet uploaded, or one that was rejected.</p>
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="profile_document_type_id" class="form-label">Document Type <span class="text-danger">*</span></label>
                    <select class="form-select" id="profile_document_type_id" name="document_type_id" required>
                        <option value="">Select document type</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error="document_type_id"></div>
                </div>
                <div class="col-md-5">
                    <label for="profile_document_file" class="form-label" id="profile_document_file_label">File (PDF, JPG, PNG — max 5MB) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="profile_document_file" name="file" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100" id="profileDocumentSubmit">Upload</button>
                </div>
            </div>
            <div class="form-text d-none" id="profile_document_file_hint"></div>
            <div class="invalid-feedback d-block" data-error="file"></div>
            <div class="invalid-feedback d-block" data-error="files"></div>
            <span class="text-success small d-none mt-2 d-block" id="profileDocumentStatus"></span>
        </form>

        <div class="profile-info-card">
            <h4 class="profile-info-card-title">My Uploaded Documents</h4>
            <div class="table-responsive">
                <table class="table profile-documents-table mb-0">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Review Notes</th>
                            <th>Uploaded</th>
                            <th>Reviewed By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="profileDocumentsTableBody">
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No documents uploaded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="profile-info-card mt-4">
            <h4 class="profile-info-card-title">Required Document Types</h4>
            <div id="profileRequiredDocuments" class="profile-required-docs"></div>
        </div>
    </div>
</div>

<!-- Document lightbox (Facebook-style full-screen viewer) -->
<div id="viewDocumentLightbox" class="document-lightbox d-none" role="dialog" aria-modal="true" aria-labelledby="viewDocumentLightboxTitle">
    <div class="document-lightbox-toolbar">
        <h2 class="document-lightbox-title" id="viewDocumentLightboxTitle">Document Preview</h2>
        <div class="document-lightbox-actions">
            <button type="button" class="document-lightbox-action-btn" id="viewDocumentOpenTab" title="Open in new tab">
                <span aria-hidden="true">↗</span>
                <span class="visually-hidden">Open in new tab</span>
            </button>
            <button type="button" class="document-lightbox-close" id="viewDocumentLightboxClose" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <div class="document-lightbox-stage">
        <iframe id="viewDocumentFrame" class="document-lightbox-frame d-none" title="Document preview"></iframe>
        <img id="viewDocumentImage" class="document-lightbox-image d-none" alt="Document preview">
        <div id="viewDocumentUnsupported" class="document-lightbox-unsupported d-none">
            <p class="mb-3">This file type cannot be previewed in the browser.</p>
            <button type="button" class="btn btn-light btn-sm" id="viewDocumentFallbackDownload">Download file</button>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectDocumentModal" tabindex="-1" aria-labelledby="rejectDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectDocumentModalLabel">Reject Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectDocumentForm">
                <div class="modal-body">
                    <p class="text-muted small">Provide a reason so the employee knows what to fix before re-uploading.</p>
                    <label for="rejectDocumentNotes" class="form-label">Rejection reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejectDocumentNotes" rows="4" required minlength="5" maxlength="1000" placeholder="Explain why this document was rejected..."></textarea>
                    <div class="invalid-feedback d-block" data-error="notes"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="rejectDocumentSubmit">Reject Document</button>
                </div>
            </form>
        </div>
    </div>
</div>
