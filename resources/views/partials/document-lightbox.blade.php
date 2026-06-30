<div id="viewDocumentLightbox" class="document-lightbox d-none" role="dialog" aria-modal="true" aria-labelledby="viewDocumentLightboxTitle">
    <div class="document-lightbox-toolbar">
        <h2 class="document-lightbox-title" id="viewDocumentLightboxTitle">Attachment Preview</h2>
        <div class="document-lightbox-actions">
            <button type="button" class="document-lightbox-action-btn" id="viewDocumentOpenTab" title="Open in new tab">
                <span aria-hidden="true">↗</span>
            </button>
            <button type="button" class="document-lightbox-close" id="viewDocumentLightboxClose" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <div class="document-lightbox-stage">
        <iframe id="viewDocumentFrame" class="document-lightbox-frame d-none" title="Attachment preview"></iframe>
        <img id="viewDocumentImage" class="document-lightbox-image d-none" alt="Attachment preview">
        <div id="viewDocumentUnsupported" class="document-lightbox-unsupported d-none">
            <p class="mb-3">This file type cannot be previewed in the browser.</p>
            <button type="button" class="btn btn-light btn-sm" id="viewDocumentFallbackDownload">Download file</button>
        </div>
    </div>
</div>
