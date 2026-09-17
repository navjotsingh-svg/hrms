import api, { getErrorMessage } from './api';

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('companyPolicyShowRoot');
    if (!root) {
        return;
    }

    const policyId = root.dataset.policyId;
    const canManage = root.dataset.canManage === '1';
    const alertBox = document.getElementById('companyPolicyShowAlert');
    const metaEl = document.getElementById('companyPolicyMeta');
    const contentEl = document.getElementById('companyPolicyContent');
    const consentCard = document.getElementById('companyPolicyConsentCard');
    const consentedCard = document.getElementById('companyPolicyConsentedCard');
    const consentedDetails = document.getElementById('companyPolicyConsentedDetails');
    const form = document.getElementById('companyPolicyConsentForm');
    const consentEmail = document.getElementById('consentEmail');
    const consentEmailHint = document.getElementById('consentEmailHint');
    const signatureName = document.getElementById('consentSignatureName');
    const canvas = document.getElementById('consentSignatureCanvas');
    const clearBtn = document.getElementById('consentSignatureClearBtn');
    const submitBtn = document.getElementById('consentSubmitBtn');

    let policy = null;
    let personalEmail = '';
    let employeeName = '';
    let drawing = false;
    let ctx = null;
    let canvasBound = false;
    let lastCanvasCssWidth = 0;

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const applyCanvasStroke = () => {
        if (!ctx) {
            return;
        }

        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2.25;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    };

    const resizeCanvas = () => {
        if (!canvas || !ctx) {
            return;
        }

        const rect = canvas.getBoundingClientRect();
        const cssWidth = Math.max(Math.floor(rect.width), 1);
        const cssHeight = 180;

        if (cssWidth < 2) {
            return;
        }

        const dpr = window.devicePixelRatio || 1;
        const nextWidth = Math.floor(cssWidth * dpr);
        const nextHeight = Math.floor(cssHeight * dpr);

        if (canvas.width === nextWidth && canvas.height === nextHeight) {
            return;
        }

        lastCanvasCssWidth = cssWidth;
        canvas.width = nextWidth;
        canvas.height = nextHeight;
        canvas.style.width = `${cssWidth}px`;
        canvas.style.height = `${cssHeight}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        applyCanvasStroke();
    };

    const setupCanvas = () => {
        if (!canvas || canvasBound) {
            return;
        }

        ctx = canvas.getContext('2d');
        applyCanvasStroke();

        const getPos = (event) => {
            const bounds = canvas.getBoundingClientRect();
            const source = event.touches?.[0] || event.changedTouches?.[0] || event;

            return {
                x: source.clientX - bounds.left,
                y: source.clientY - bounds.top,
            };
        };

        const start = (event) => {
            resizeCanvas();
            drawing = true;
            const pos = getPos(event);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            event.preventDefault();
        };

        const draw = (event) => {
            if (!drawing) {
                return;
            }

            const pos = getPos(event);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            event.preventDefault();
        };

        const stop = () => {
            drawing = false;
        };

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stop);
        canvas.addEventListener('mouseleave', stop);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stop);
        canvas.addEventListener('touchcancel', stop);
        window.addEventListener('resize', () => {
            if (!consentCard?.classList.contains('d-none')) {
                resizeCanvas();
            }
        });

        canvasBound = true;
    };

    const clearCanvas = () => {
        if (!canvas || !ctx) {
            return;
        }

        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.restore();
        applyCanvasStroke();
    };

    const canvasHasInk = () => {
        if (!canvas || !ctx || canvas.width < 2 || canvas.height < 2) {
            return false;
        }

        const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (let i = 3; i < data.length; i += 4) {
            if (data[i] > 0) {
                return true;
            }
        }

        return false;
    };

    const protectContent = () => {
        if (!contentEl) {
            return;
        }

        contentEl.addEventListener('copy', (event) => event.preventDefault());
        contentEl.addEventListener('cut', (event) => event.preventDefault());
        contentEl.addEventListener('contextmenu', (event) => event.preventDefault());
        contentEl.addEventListener('dragstart', (event) => event.preventDefault());
    };

    const render = () => {
        if (!policy) {
            return;
        }

        document.getElementById('companyPolicyShowTitle').textContent = policy.title;
        document.getElementById('companyPolicyShowSubtitle').textContent = `${policy.category_label || ''} · Version ${policy.version}`;

        metaEl.innerHTML = `
            <div class="row g-3">
                <div class="col-md-3"><div class="small text-muted">Status</div><div>${escapeHtml(policy.status_label)}</div></div>
                <div class="col-md-3"><div class="small text-muted">Category</div><div>${escapeHtml(policy.category_label)}</div></div>
                <div class="col-md-3"><div class="small text-muted">Updated</div><div>${escapeHtml(policy.updated_at_label || '—')}</div></div>
                <div class="col-md-3"><div class="small text-muted">Consent</div><div>${policy.requires_consent ? (policy.has_consented ? 'Completed' : 'Required') : 'Not required'}</div></div>
            </div>
            ${policy.description ? `<p class="mb-0 mt-3 text-muted">${escapeHtml(policy.description)}</p>` : ''}
        `;

        contentEl.innerHTML = policy.body_html || '<p class="text-muted mb-0">No policy content yet.</p>';

        const showConsentForm = !canManage && policy.needs_consent;
        consentCard?.classList.toggle('d-none', !showConsentForm);
        consentedCard?.classList.toggle('d-none', !policy.has_consented);

        if (policy.has_consented && policy.my_consent) {
            consentedDetails.innerHTML = `
                <div class="row g-3">
                    <div class="col-md-4"><div class="small text-muted">Personal email</div><div>${escapeHtml(policy.my_consent.consent_email)}</div></div>
                    <div class="col-md-4"><div class="small text-muted">Signed name</div><div>${escapeHtml(policy.my_consent.signature_name)}</div></div>
                    <div class="col-md-4"><div class="small text-muted">Signed at</div><div>${escapeHtml(policy.my_consent.signed_at_label || '—')}</div></div>
                    ${policy.my_consent.signature_image_url ? `<div class="col-12"><div class="small text-muted mb-1">Signature</div><img src="${escapeHtml(policy.my_consent.signature_image_url)}" alt="Signature" class="company-policy-signature-preview"></div>` : ''}
                </div>
            `;
        }

        if (showConsentForm) {
            if (personalEmail) {
                consentEmail.value = personalEmail;
                consentEmailHint.textContent = `Must match your profile personal email (${personalEmail}).`;
            } else {
                consentEmailHint.textContent = 'Add your personal email in Profile before giving consent.';
            }

            if (employeeName && !signatureName.value) {
                signatureName.value = employeeName;
            }

            // Consent card starts hidden; size the pad only after it is visible.
            requestAnimationFrame(() => {
                setupCanvas();
                resizeCanvas();
            });
        }
    };

    protectContent();
    clearBtn?.addEventListener('click', clearCanvas);

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!personalEmail) {
            showAlert('Add your personal email in Profile before giving consent.', 'warning');
            return;
        }

        if (!canvasHasInk()) {
            showAlert('Please draw your signature.', 'warning');
            return;
        }

        submitBtn.disabled = true;
        const original = submitBtn.textContent;
        submitBtn.textContent = 'Submitting...';

        try {
            const { data } = await api.post(`/company-policies/${policyId}/consent`, {
                consent_email: consentEmail.value.trim(),
                signature_name: signatureName.value.trim(),
                signature_data_url: canvas.toDataURL('image/png'),
            });

            policy = data.data?.policy || policy;
            showAlert(data.message || 'Consent recorded successfully.');
            render();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = original;
        }
    });

    try {
        const [metaResponse, policyResponse] = await Promise.all([
            api.get('/company-policies/meta'),
            api.get(`/company-policies/${policyId}`),
        ]);

        personalEmail = metaResponse.data.data?.personal_email || '';
        employeeName = metaResponse.data.data?.employee_name || '';
        policy = policyResponse.data.data?.policy;

        if (!policy) {
            throw new Error('Policy not found.');
        }

        render();
    } catch (error) {
        metaEl.innerHTML = `<div class="text-danger text-center py-4">${escapeHtml(getErrorMessage(error))}</div>`;
        showAlert(getErrorMessage(error), 'danger');
    }
});
