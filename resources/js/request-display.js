import { isPublicAttachmentUrl, normalizePublicAssetUrl } from './request-attachments';
import { renderDateTimeStackFromLabel } from './datetime-utils';

export const renderEmployeeNameBlock = (name, code) => {
    const displayName = name || 'Employee';

    return `
        <div class="fw-semibold">${displayName}</div>
        ${code ? `<div class="small text-muted">${code}</div>` : ''}
    `.trim();
};

export const formatPunchPair = (inLabel, outLabel, { emptyLabel = '—', missingOutLabel = null } = {}) => {
    const parts = [];

    if (inLabel) {
        parts.push(`Login ${inLabel}`);
    }

    if (outLabel) {
        parts.push(`Logout ${outLabel}`);
    } else if (missingOutLabel && inLabel) {
        parts.push(missingOutLabel);
    }

    return parts.join(' · ') || emptyLabel;
};

export const formatOriginalPunchLine = (item) => formatPunchPair(
    item?.original_punch_in_label,
    item?.original_punch_out_label,
    { missingOutLabel: 'Logout not recorded' },
);

export const formatRequestedPunchLine = (item) => formatPunchPair(
    item?.requested_punch_in_label,
    item?.requested_punch_out_label,
);

export const renderRegularizationPunchCompare = (item, { compact = false } = {}) => {
    const oldIn = item?.original_punch_in_label || '—';
    const oldOut = item?.has_original_punch_out === false || (!item?.original_punch_out_label && item?.original_punch_in_label)
        ? (item?.original_punch_in_label ? 'Not recorded' : '—')
        : (item?.original_punch_out_label || '—');
    const newIn = item?.requested_punch_in_label || '—';
    const newOut = item?.requested_punch_out_label || '—';

    return `
        <div class="regularization-punch-compare${compact ? ' regularization-punch-compare--compact' : ''}">
            <div class="regularization-punch-compare-col">
                <div class="regularization-punch-compare-head">Old</div>
                <div class="regularization-punch-compare-line">
                    <span class="regularization-punch-compare-label">In</span>
                    <span class="regularization-punch-compare-value">${oldIn}</span>
                </div>
                <div class="regularization-punch-compare-line">
                    <span class="regularization-punch-compare-label">Out</span>
                    <span class="regularization-punch-compare-value">${oldOut}</span>
                </div>
            </div>
            <div class="regularization-punch-compare-col">
                <div class="regularization-punch-compare-head">Regularization</div>
                <div class="regularization-punch-compare-line">
                    <span class="regularization-punch-compare-label">In</span>
                    <span class="regularization-punch-compare-value">${newIn}</span>
                </div>
                <div class="regularization-punch-compare-line">
                    <span class="regularization-punch-compare-label">Out</span>
                    <span class="regularization-punch-compare-value">${newOut}</span>
                </div>
            </div>
        </div>
    `.trim();
};

export const renderRegularizationPunchFields = (item) => `
    <div class="col-12">
        <span class="text-muted d-block mb-2">Attendance Times</span>
        ${renderRegularizationPunchCompare(item)}
    </div>
`;

const renderRegularizationReadonlyDateRow = (day) => {
    const label = day.attendance_date_label || day.attendance_date || '—';
    const statusBadge = day.status
        ? `<span class="badge text-bg-${day.status === 'approved' ? 'success' : day.status === 'rejected' ? 'danger' : day.status === 'pending' ? 'warning' : 'secondary'} ms-2">${day.status_label || day.status}</span>`
        : '';
    const reviewMeta = day.review_notes
        ? `<div class="small text-muted mt-1"><span class="fw-semibold">Remarks:</span> ${day.review_notes}</div>`
        : '';
    const reviewedMeta = day.reviewed_at_label
        ? `<div class="small text-muted mt-1">Reviewed ${day.reviewed_at_label}${day.reviewed_by_name ? ` by ${day.reviewed_by_name}` : ''}</div>`
        : '';

    return `
        <li class="regularization-batch-date-item">
            <div class="fw-semibold mb-2">${label}${statusBadge}</div>
            ${renderRegularizationPunchCompare(day, { compact: true })}
            ${reviewMeta}
            ${reviewedMeta}
        </li>
    `;
};

export const renderRegularizationBatchDates = (dates = [], { showStatus = false } = {}) => {
    if (!dates.length) {
        return '<li class="text-muted">—</li>';
    }

    if (!showStatus) {
        return dates.map((day) => {
            const label = day.attendance_date_label || day.attendance_date || '—';

            return `
                <li class="regularization-batch-date-item">
                    <div class="fw-semibold mb-2">${label}</div>
                    ${renderRegularizationPunchCompare(day, { compact: true })}
                </li>
            `;
        }).join('');
    }

    return dates.map(renderRegularizationReadonlyDateRow).join('');
};

const renderRegularizationReviewRow = (day) => `
    <div class="regularization-batch-review-row regularization-batch-date-item" data-request-id="${day.id}">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" data-regularization-select checked id="reg-review-${day.id}">
            <label class="form-check-label fw-semibold" for="reg-review-${day.id}">${day.attendance_date_label || '—'}</label>
        </div>
        ${renderRegularizationPunchCompare(day, { compact: true })}
        <div class="row g-2 mt-2 align-items-start">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Action</label>
                <select class="form-select form-select-sm" data-regularization-action>
                    <option value="approve">Approve</option>
                    <option value="reject">Reject</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label small text-muted mb-1">Remarks</label>
                <textarea class="form-control form-control-sm" rows="2" placeholder="Required when rejecting" data-regularization-notes></textarea>
            </div>
        </div>
    </div>
`;

export const hasRegularizationBatchReviewPanel = (dates = []) => (
    dates.some((day) => day.status === 'pending' && day.can_review)
);

export const renderRegularizationBatchReviewPanel = (dates = [], { embedded = false } = {}) => {
    const pendingReviewable = dates.filter((day) => day.status === 'pending' && day.can_review);
    const readonlyDates = dates.filter((day) => !(day.status === 'pending' && day.can_review));

    if (!pendingReviewable.length) {
        return '';
    }

    const readonlyBlock = readonlyDates.length
        ? `<ul class="mb-0 ps-3 regularization-batch-readonly-dates">${readonlyDates.map(renderRegularizationReadonlyDateRow).join('')}</ul>`
        : '';

    return `
        <div class="${embedded ? '' : 'col-12 '}regularization-batch-review-panel border rounded p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="fw-semibold">Review selected days</div>
                    <div class="small text-muted">Select days, choose approve or reject, and add remarks for each request.</div>
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" data-regularization-select-all>Select all</button>
                    <button type="button" class="btn btn-outline-secondary" data-regularization-select-none>Clear all</button>
                </div>
            </div>
            <div class="regularization-batch-review-rows">
                ${pendingReviewable.map(renderRegularizationReviewRow).join('')}
            </div>
            ${readonlyBlock}
            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-primary btn-sm" data-regularization-submit-review>Submit review</button>
            </div>
        </div>
    `;
};

export const renderRegularizationBatchDatesSection = (dates = []) => {
    if (!dates.length) {
        return `
            <div class="col-12">
                <span class="text-muted">Dates</span>
                <ul class="mb-0 ps-3"><li class="text-muted">—</li></ul>
            </div>
        `;
    }

    if (hasRegularizationBatchReviewPanel(dates)) {
        return `
            <div class="col-12">
                <span class="text-muted d-block mb-2">Dates</span>
                ${renderRegularizationBatchReviewPanel(dates, { embedded: true })}
            </div>
        `;
    }

    return `
        <div class="col-12">
            <span class="text-muted">Dates</span>
            <ul class="mb-0 ps-3">${renderRegularizationBatchDates(dates, { showStatus: true })}</ul>
        </div>
    `;
};

const escapeAttr = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');

const isImageAttachment = (file) => {
    const mime = String(file.mime_type || '').toLowerCase();

    if (mime.startsWith('image/')) {
        return true;
    }

    const name = String(file.original_name || file.label || '').toLowerCase();

    return /\.(jpe?g|png|webp|gif)$/.test(name);
};

const renderAttachmentLink = (file) => {
    const label = file.label || file.original_name || 'Attachment';
    const downloadUrl = file.download_url || file.file_url || file.url || '';

    return `
        <li class="mb-1">
            <button
                type="button"
                class="btn btn-link btn-sm p-0 align-baseline request-attachment-link"
                data-request-attachment="${escapeAttr(downloadUrl)}"
                data-request-attachment-label="${escapeAttr(label)}"
            >${label}</button>
        </li>
    `;
};

export const renderRequestAttachmentGallery = (attachments = [], { emptyLabel = 'No attachments' } = {}) => {
    if (!attachments.length) {
        return `
            <div class="col-12">
                <span class="text-muted">Attachments</span>
                <div class="text-muted small mt-1">${emptyLabel}</div>
            </div>
        `;
    }

    const images = attachments.filter(isImageAttachment);
    const others = attachments.filter((file) => !isImageAttachment(file));

    const imageGrid = images.map((file) => {
        const label = file.original_name || file.label || 'Attachment';
        const rawUrl = file.download_url || file.file_url || file.url || '';
        const downloadUrl = isPublicAttachmentUrl(rawUrl)
            ? normalizePublicAssetUrl(rawUrl)
            : rawUrl;
        const srcAttr = isPublicAttachmentUrl(rawUrl)
            ? ` src="${escapeAttr(downloadUrl)}"`
            : '';

        return `
            <button
                type="button"
                class="request-attachment-image-preview"
                data-request-attachment="${escapeAttr(downloadUrl)}"
                data-request-attachment-label="${escapeAttr(label)}"
                title="${escapeAttr(label)}"
            >
                <img
                    class="request-attachment-image-thumb"
                    data-request-attachment-preview="${escapeAttr(downloadUrl)}"
                    alt="${escapeAttr(label)}"
                    loading="lazy"${srcAttr}
                >
            </button>
        `;
    }).join('');

    const otherList = others.map(renderAttachmentLink).join('');

    return `
        <div class="col-12">
            <span class="text-muted">Attachments</span>
            ${images.length ? `<div class="request-attachment-image-grid mt-2">${imageGrid}</div>` : ''}
            ${others.length ? `<ul class="mb-0 ps-3${images.length ? ' mt-2' : ' mt-1'}">${otherList}</ul>` : ''}
        </div>
    `;
};

export const renderRequestAttachments = (attachments = []) => {
    if (!attachments.length) {
        return '';
    }

    const items = attachments.map((file) => renderAttachmentLink(file).trim()).join('');

    return `
        <div class="col-12">
            <span class="text-muted">Attachments</span>
            <ul class="mb-0 ps-3">${items}</ul>
        </div>
    `;
};

export const renderRequestFields = (fields = []) => {
    if (!fields.length) {
        return '';
    }

    return fields.map((field) => `
        <div class="col-md-6">
            <span class="text-muted">${field.label || 'Field'}</span>
            <div>${field.value || '—'}</div>
        </div>
    `).join('');
};

const renderProfilePhotoPreview = (attachments = []) => {
    const file = attachments[0];
    const downloadUrl = file?.download_url || file?.file_url || file?.url || '';

    if (!downloadUrl) {
        return '';
    }

    return `
        <div class="col-12">
            <span class="text-muted">Photo</span>
            <div class="mt-2">
                <button
                    type="button"
                    class="request-profile-photo-preview request-attachment-link"
                    data-request-attachment="${escapeAttr(downloadUrl)}"
                    data-request-attachment-label="Profile photo"
                    title="View profile photo"
                >
                    <img
                        class="request-profile-photo-thumb"
                        data-profile-photo-preview="${escapeAttr(downloadUrl)}"
                        alt="Submitted profile photo"
                    >
                </button>
            </div>
        </div>
    `;
};

export const renderHubRequestDetailHtml = (item) => {
    if (item.category === 'profile_photo') {
        return `
            <div class="row g-4">
                <div class="col-md-6">
                    <span class="text-muted">Request Type</span>
                    <div class="fw-semibold">${item.category_label || 'Profile Photo'}</div>
                </div>
                <div class="col-md-6">
                    <span class="text-muted">Requested By</span>
                    ${renderEmployeeNameBlock(item.requester_name, item.requester_code)}
                </div>
                <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${renderDateTimeStackFromLabel(item.submitted_at_label)}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
                ${item.reason ? `<div class="col-12"><span class="text-muted">Reason / Notes</span><div>${item.reason}</div></div>` : ''}
                ${item.reviewed_at_label ? `<div class="col-12"><span class="text-muted">Reviewed</span><div>${renderDateTimeStackFromLabel(item.reviewed_at_label)}${item.reviewed_by_name ? ` by ${item.reviewed_by_name}` : ''}</div></div>` : ''}
                ${renderProfilePhotoPreview(item.attachments || [])}
            </div>
        `;
    }

    return `
    <div class="row g-4">
        <div class="col-md-6">
            <span class="text-muted">Request Type</span>
            <div class="fw-semibold">${item.document_type_name || item.category_label || 'Request'}</div>
        </div>
        <div class="col-md-6">
            <span class="text-muted">Requested By</span>
            ${renderEmployeeNameBlock(item.requester_name, item.requester_code)}
        </div>
        <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${renderDateTimeStackFromLabel(item.submitted_at_label)}</div></div>
        <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
        ${renderRequestFields(item.fields || [])}
        ${item.detail && !(item.fields || []).length ? `<div class="col-12"><span class="text-muted">Details</span><div>${item.detail}</div></div>` : ''}
        ${item.subject && item.subject !== item.category_label ? `<div class="col-12"><span class="text-muted">Subject</span><div>${item.subject}</div></div>` : ''}
        ${item.reason ? `<div class="col-12"><span class="text-muted">Reason / Notes</span><div>${item.reason}</div></div>` : ''}
        ${item.reviewed_at_label ? `<div class="col-12"><span class="text-muted">Reviewed</span><div>${renderDateTimeStackFromLabel(item.reviewed_at_label)}${item.reviewed_by_name ? ` by ${item.reviewed_by_name}` : ''}</div></div>` : ''}
        ${renderRequestAttachments(item.attachments || [])}
    </div>
`;
};
