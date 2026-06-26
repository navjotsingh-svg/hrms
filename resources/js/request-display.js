export const renderEmployeeNameBlock = (name, code) => {
    const displayName = name || 'Employee';

    return `
        <div class="fw-semibold">${displayName}</div>
        ${code ? `<div class="small text-muted">${code}</div>` : ''}
    `.trim();
};

export const formatPunchPair = (inLabel, outLabel, { emptyLabel = '—' } = {}) => {
    const parts = [];

    if (inLabel) {
        parts.push(`Login ${inLabel}`);
    }

    if (outLabel) {
        parts.push(`Logout ${outLabel}`);
    }

    return parts.join(' · ') || emptyLabel;
};

export const formatOriginalPunchLine = (item) => formatPunchPair(
    item?.original_punch_in_label,
    item?.original_punch_out_label,
);

export const formatRequestedPunchLine = (item) => formatPunchPair(
    item?.requested_punch_in_label,
    item?.requested_punch_out_label,
);

export const renderRegularizationPunchFields = (item) => `
    <div class="col-md-6">
        <span class="text-muted">Login</span>
        <div>${item.original_punch_in_label || '—'}</div>
    </div>
    <div class="col-md-6">
        <span class="text-muted">Logout</span>
        <div>${item.original_punch_out_label || '—'}</div>
    </div>
    <div class="col-md-6">
        <span class="text-muted">Requested Login</span>
        <div>${item.requested_punch_in_label || '—'}</div>
    </div>
    <div class="col-md-6">
        <span class="text-muted">Requested Logout</span>
        <div>${item.requested_punch_out_label || '—'}</div>
    </div>
`;

export const renderRegularizationBatchDates = (dates = []) => {
    if (!dates.length) {
        return '<li class="text-muted">—</li>';
    }

    return dates.map((day) => {
        const label = day.attendance_date_label || day.attendance_date || '—';
        const original = formatOriginalPunchLine(day);
        const requested = formatRequestedPunchLine(day);

        return `
            <li class="mb-2">
                <div class="fw-semibold">${label}</div>
                <div class="small text-muted">Login / Logout: ${original}</div>
                <div class="small text-muted">Requested: ${requested}</div>
            </li>
        `;
    }).join('');
};

const isImageMime = (mime = '') => mime.startsWith('image/');

export const renderRequestAttachments = (attachments = []) => {
    if (!attachments.length) {
        return '';
    }

    const items = attachments.map((file) => {
        const label = file.label || file.original_name || 'Attachment';
        const href = file.download_url || file.file_url || file.url || '#';
        const previewUrl = file.file_url || file.url;

        if (previewUrl && isImageMime(file.mime_type)) {
            return `
                <li class="mb-2">
                    <a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>
                    <div class="mt-2">
                        <a href="${href}" target="_blank" rel="noopener noreferrer">
                            <img src="${previewUrl}" alt="${label}" class="img-fluid rounded border" style="max-height:220px">
                        </a>
                    </div>
                </li>
            `;
        }

        return `<li class="mb-1"><a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a></li>`;
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

export const renderHubRequestDetailHtml = (item) => `
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
