const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const hasFaceVerification = (punch) => punch.has_face_verification === true
    || (punch.face_match_score !== null && punch.face_match_score !== undefined && punch.face_match_score !== '');

const formatFaceMatch = (score, threshold = 80) => {
    if (score === null || score === undefined || score === '') {
        return '—';
    }

    const numeric = Number(score);

    if (Number.isNaN(numeric)) {
        return '—';
    }

    const className = numeric >= threshold ? 'text-success' : 'text-danger';

    return `<span class="${className} fw-semibold">${numeric}%</span>`;
};

export const renderAttendancePunchVerification = (punch, { compact = false, threshold = 80 } = {}) => {
    const ip = punch.ip_address ? escapeHtml(punch.ip_address) : '—';
    const mac = punch.mac_address ? escapeHtml(punch.mac_address) : '—';
    const showFaceMatch = hasFaceVerification(punch);
    const faceMatch = showFaceMatch ? formatFaceMatch(punch.face_match_score, threshold) : null;

    if (compact) {
        const parts = [];

        if (showFaceMatch) {
            parts.push(`Face ${faceMatch}`);
        }

        if (punch.ip_address) {
            parts.push(`IP ${ip}`);
        }

        if (punch.mac_address) {
            parts.push(`MAC ${mac}`);
        }

        if (!parts.length) {
            return '';
        }

        return `
            <div class="attendance-punch-verification attendance-punch-verification--compact small text-muted mt-1">
                ${parts.join(' · ')}
            </div>
        `;
    }

    if (!showFaceMatch && !punch.ip_address && !punch.mac_address) {
        return '';
    }

    const faceMatchBlock = showFaceMatch
        ? `
                <div class="col-sm-4">
                    <div class="text-muted mb-1">Face match</div>
                    <div>${faceMatch}</div>
                </div>
        `
        : '';

    return `
        <div class="attendance-punch-verification mt-2 pt-2 border-top">
            <div class="row g-2 small">
                ${faceMatchBlock}
                <div class="col-sm-4">
                    <div class="text-muted mb-1">IP address</div>
                    <div class="font-monospace">${ip}</div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted mb-1">MAC address</div>
                    <div class="font-monospace">${mac}</div>
                </div>
            </div>
        </div>
    `;
};

export const renderAttendancePunchCard = (punch, {
    formatDateTime = (value) => value || '—',
    includeSelfie = true,
    threshold = 80,
} = {}) => {
    const selfieBlock = includeSelfie && punch.selfie_url
        ? `<a href="${escapeHtml(punch.selfie_url)}" target="_blank" rel="noopener" class="small">Open selfie</a>`
        : (includeSelfie ? '<span class="small text-muted">Regularized</span>' : '');
    const imageBlock = includeSelfie && punch.selfie_url
        ? `<div class="col-md-4"><img src="${escapeHtml(punch.selfie_url)}" alt="${escapeHtml(punch.punch_label)} selfie" class="attendance-selfie-thumb"></div>`
        : '';
    const verificationBlock = renderAttendancePunchVerification(punch, { threshold });
    const faceMatchBadge = hasFaceVerification(punch)
        ? `<span class="badge ${Number(punch.face_match_score) >= threshold ? 'text-bg-success' : 'text-bg-warning'} ms-1">${escapeHtml(String(punch.face_match_score))}% face match</span>`
        : '';

    return `
        <div class="attendance-punch-card">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <span class="badge ${punch.punch_type === 'in' ? 'text-bg-success' : 'text-bg-warning'}">${escapeHtml(punch.punch_label)}</span>
                    ${punch.is_regularized ? '<span class="badge text-bg-info ms-1">Regularized</span>' : ''}
                    ${faceMatchBadge}
                    <span class="ms-2 fw-semibold">${escapeHtml(formatDateTime(punch.punched_at))}</span>
                </div>
                ${selfieBlock}
            </div>
            <div class="row g-3 align-items-start">
                ${imageBlock}
                <div class="${imageBlock ? 'col-md-8' : 'col-12'}">
                    <div class="small text-muted mb-1">Location</div>
                    <div class="mb-2">${escapeHtml(punch.location_label || '—')}</div>
                    ${punch.latitude || punch.longitude
                        ? `<a href="https://www.google.com/maps?q=${encodeURIComponent(punch.latitude)},${encodeURIComponent(punch.longitude)}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">View on map</a>`
                        : ''}
                    ${verificationBlock}
                </div>
            </div>
        </div>
    `;
};
