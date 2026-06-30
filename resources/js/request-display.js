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
    const oldOut = item?.original_punch_out_label || (item?.original_punch_in_label ? 'Not recorded' : '—');
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

export const renderRegularizationBatchDates = (dates = []) => {
    if (!dates.length) {
        return '<li class="text-muted">—</li>';
    }

    return dates.map((day) => {
        const label = day.attendance_date_label || day.attendance_date || '—';

        return `
            <li class="regularization-batch-date-item">
                <div class="fw-semibold mb-2">${label}</div>
                ${renderRegularizationPunchCompare(day, { compact: true })}
            </li>
        `;
    }).join('');
};

const escapeAttr = (value = '') => String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;');

export const renderRequestAttachments = (attachments = []) => {
    if (!attachments.length) {
        return '';
    }

    const items = attachments.map((file) => {
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
    }).join('');

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
                <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${item.submitted_at_label || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
                ${item.reason ? `<div class="col-12"><span class="text-muted">Reason / Notes</span><div>${item.reason}</div></div>` : ''}
                ${item.reviewed_at_label ? `<div class="col-12"><span class="text-muted">Reviewed</span><div>${item.reviewed_at_label}${item.reviewed_by_name ? ` by ${item.reviewed_by_name}` : ''}</div></div>` : ''}
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
        <div class="col-md-6"><span class="text-muted">Submitted On</span><div>${item.submitted_at_label || '—'}</div></div>
        <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label || item.status}</div></div>
        ${renderRequestFields(item.fields || [])}
        ${item.detail && !(item.fields || []).length ? `<div class="col-12"><span class="text-muted">Details</span><div>${item.detail}</div></div>` : ''}
        ${item.subject && item.subject !== item.category_label ? `<div class="col-12"><span class="text-muted">Subject</span><div>${item.subject}</div></div>` : ''}
        ${item.reason ? `<div class="col-12"><span class="text-muted">Reason / Notes</span><div>${item.reason}</div></div>` : ''}
        ${item.reviewed_at_label ? `<div class="col-12"><span class="text-muted">Reviewed</span><div>${item.reviewed_at_label}${item.reviewed_by_name ? ` by ${item.reviewed_by_name}` : ''}</div></div>` : ''}
        ${renderRequestAttachments(item.attachments || [])}
    </div>
`;
};
