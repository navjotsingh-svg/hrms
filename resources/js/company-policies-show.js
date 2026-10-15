import api, { getErrorMessage } from './api';
import { unwrapEscapedPolicyHtml } from './company-policy-html';

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
    const signaturesCard = document.getElementById('companyPolicySignaturesCard');
    const signaturesList = document.getElementById('companyPolicySignaturesList');
    const form = document.getElementById('companyPolicyConsentForm');
    const consentEmail = document.getElementById('consentEmail');
    const consentEmailHint = document.getElementById('consentEmailHint');
    const signatureName = document.getElementById('consentSignatureName');
    const canvas = document.getElementById('consentSignatureCanvas');
    const clearBtn = document.getElementById('consentSignatureClearBtn');
    const submitBtn = document.getElementById('consentSubmitBtn');
    const typedSignature = document.getElementById('consentTypedSignature');
    const fontSelect = document.getElementById('consentSignatureFont');
    const typedPreview = document.getElementById('consentTypedSignaturePreview');
    const signatureFile = document.getElementById('consentSignatureFile');
    const signatureFilePreview = document.getElementById('consentSignatureFilePreview');
    const signatureModes = document.querySelectorAll('[data-signature-mode]');
    const signaturePanels = document.querySelectorAll('[data-signature-panel]');
    const signatureFonts = [
        { id: 'Great Vibes', label: 'Great Vibes' },
        { id: 'Allura', label: 'Allura' },
        { id: 'Dancing Script', label: 'Dancing Script' },
        { id: 'Sacramento', label: 'Sacramento' },
        { id: 'Caveat', label: 'Caveat' },
        { id: 'Pacifico', label: 'Pacifico' },
    ];
    let signatureMode = 'draw';

    let policy = null;
    let consents = [];
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

    const setSignatureMode = (mode) => {
        signatureMode = mode;
        signatureModes.forEach((button) => {
            button.classList.toggle('active', button.dataset.signatureMode === mode);
        });
        signaturePanels.forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.signaturePanel !== mode);
        });

        if (mode === 'draw') {
            requestAnimationFrame(() => {
                setupCanvas();
                resizeCanvas();
            });
        }

        if (mode === 'write') {
            if (typedSignature && !typedSignature.value.trim() && signatureName?.value) {
                typedSignature.value = signatureName.value;
            }
            updateTypedPreview();
        }
    };

    const selectedFont = () => fontSelect?.value || signatureFonts[0].id;

    const updateTypedPreview = () => {
        if (!typedPreview) {
            return;
        }

        const text = typedSignature?.value.trim() || 'Your signature';
        const font = selectedFont();
        typedPreview.textContent = text;
        typedPreview.style.fontFamily = `"${font}", cursive`;
        typedPreview.style.fontSize = font === 'Pacifico' || font === 'Caveat' ? '2.4rem' : '3rem';
        if (fontSelect) {
            fontSelect.style.fontFamily = `"${font}", cursive`;
        }
    };

    const renderTypedSignature = async () => {
        const text = typedSignature?.value.trim() || '';
        const font = selectedFont();

        if (!text) {
            return '';
        }

        if (document.fonts?.load) {
            await document.fonts.load(`72px "${font}"`);
        }

        const exportCanvas = document.createElement('canvas');
        exportCanvas.width = 900;
        exportCanvas.height = 280;
        const exportCtx = exportCanvas.getContext('2d');
        exportCtx.fillStyle = '#ffffff';
        exportCtx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
        exportCtx.fillStyle = '#111827';
        exportCtx.textAlign = 'center';
        exportCtx.textBaseline = 'middle';

        let size = 96;
        exportCtx.font = `${size}px "${font}", cursive`;
        while (exportCtx.measureText(text).width > exportCanvas.width - 80 && size > 36) {
            size -= 4;
            exportCtx.font = `${size}px "${font}", cursive`;
        }

        exportCtx.fillText(text, exportCanvas.width / 2, exportCanvas.height / 2);

        return exportCanvas.toDataURL('image/png');
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

    const sanitizePolicyHtml = (html) => {
        const template = document.createElement('template');
        template.innerHTML = String(html || '');
        template.content.querySelectorAll('script, iframe, object, embed, meta, base, link').forEach((node) => node.remove());
        template.content.querySelectorAll('*').forEach((node) => {
            [...node.attributes].forEach((attr) => {
                if (attr.name.toLowerCase().startsWith('on')) {
                    node.removeAttribute(attr.name);
                }
            });
        });

        return template.innerHTML.trim();
    };

    const renderPolicyPreview = (html) => {
        const safe = sanitizePolicyHtml(html);
        contentEl.replaceChildren();

        const host = document.createElement('div');
        host.className = 'company-policy-render';
        contentEl.appendChild(host);

        const shadow = host.attachShadow({ mode: 'open' });
        const markup = safe || '<p style="color:#64748b;margin:0">No policy content yet.</p>';
        shadow.innerHTML = `<style>:host{display:block}.policy-root{line-height:1.65;color:#0f172a}.policy-root img{max-width:100%;height:auto}</style><div class="policy-root">${markup}</div>`;
        const root = shadow.querySelector('.policy-root');

        ['copy', 'cut', 'contextmenu', 'dragstart'].forEach((type) => {
            root?.addEventListener(type, (event) => event.preventDefault());
        });

        root?.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            const href = link?.getAttribute('href') || '';

            if (!href.startsWith('#')) {
                return;
            }

            const id = decodeURIComponent(href.slice(1));
            const target = id ? shadow.getElementById(id) : root;

            if (!target) {
                return;
            }

            event.preventDefault();

            const topbar = document.querySelector('.topbar');
            const banner = document.querySelector('.content-area > .page-header');
            const offset = (topbar?.offsetHeight || 0) + (banner?.offsetHeight || 0) + 16;
            const top = target.getBoundingClientRect().top + window.scrollY - offset;

            window.scrollTo({
                top: Math.max(top, 0),
                behavior: 'smooth',
            });
        });
    };

    const renderSignatures = () => {
        if (!canManage || !signaturesCard || !signaturesList) {
            return;
        }

        signaturesCard.classList.remove('d-none');

        if (!consents.length) {
            signaturesList.innerHTML = '<p class="text-muted mb-0">No employee has signed this policy yet.</p>';
            return;
        }

        signaturesList.innerHTML = consents.map((consent) => `
            <article class="border rounded p-3 mb-3">
                <div class="fw-semibold">${escapeHtml(consent.employee_name)}</div>
                <div class="small text-muted">${escapeHtml([consent.employee_code, consent.consent_email].filter(Boolean).join(' · '))}</div>
                <div class="small mt-1">Signed as ${escapeHtml(consent.signature_name || '—')} on ${escapeHtml(consent.signed_at_label || '—')}</div>
                ${consent.is_current_version ? '' : `<div class="small text-warning">Signed version ${escapeHtml(consent.policy_version)}. The current policy version is newer.</div>`}
                ${consent.signature_image_url ? `<img src="${escapeHtml(consent.signature_image_url)}" alt="Signature of ${escapeHtml(consent.employee_name)}" class="company-policy-signature-preview mt-2">` : '<div class="small text-muted mt-2">Signature image is not available.</div>'}
            </article>
        `).join('');
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

        renderPolicyPreview(unwrapEscapedPolicyHtml(policy.body_html));
        renderSignatures();

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

            if (typedSignature && !typedSignature.value.trim() && signatureName.value) {
                typedSignature.value = signatureName.value;
                updateTypedPreview();
            }

            // Consent card starts hidden; size the pad only after it is visible.
            requestAnimationFrame(() => {
                setupCanvas();
                resizeCanvas();
            });
        }
    };

    if (fontSelect) {
        fontSelect.innerHTML = signatureFonts.map((font) => (
            `<option value="${escapeHtml(font.id)}" style="font-family: '${escapeHtml(font.id)}', cursive">${escapeHtml(font.label)}</option>`
        )).join('');
    }

    protectContent();
    clearBtn?.addEventListener('click', clearCanvas);
    signatureModes.forEach((button) => {
        button.addEventListener('click', () => setSignatureMode(button.dataset.signatureMode));
    });
    typedSignature?.addEventListener('input', updateTypedPreview);
    fontSelect?.addEventListener('change', updateTypedPreview);
    signatureName?.addEventListener('input', () => {
        if (signatureMode === 'write' && typedSignature && !typedSignature.dataset.edited) {
            typedSignature.value = signatureName.value;
            updateTypedPreview();
        }
    });
    typedSignature?.addEventListener('input', () => {
        typedSignature.dataset.edited = typedSignature.value.trim() ? '1' : '';
    });
    signatureFile?.addEventListener('change', () => {
        const file = signatureFile.files?.[0];
        if (!file || !signatureFilePreview) {
            signatureFilePreview?.classList.add('d-none');
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            signatureFilePreview.src = String(reader.result || '');
            signatureFilePreview.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!personalEmail) {
            showAlert('Add your personal email in Profile before giving consent.', 'warning');
            return;
        }

        let signatureDataUrl = '';
        const payload = new FormData();
        payload.append('consent_email', consentEmail.value.trim());
        payload.append('signature_name', signatureName.value.trim());
        payload.append('signature_method', signatureMode);

        if (signatureMode === 'draw') {
            if (!canvasHasInk()) {
                showAlert('Please draw your signature.', 'warning');
                return;
            }
            signatureDataUrl = canvas.toDataURL('image/png');
        } else if (signatureMode === 'write') {
            if (!typedSignature?.value.trim()) {
                showAlert('Please type your signature.', 'warning');
                return;
            }
            signatureDataUrl = await renderTypedSignature();
            payload.append('signature_font', selectedFont());
        } else {
            const file = signatureFile?.files?.[0];
            if (!file) {
                showAlert('Please upload a signature photo.', 'warning');
                return;
            }
            payload.append('signature_file', file);
        }

        if (signatureDataUrl) {
            payload.append('signature_data_url', signatureDataUrl);
        }

        submitBtn.disabled = true;
        const original = submitBtn.textContent;
        submitBtn.textContent = 'Submitting...';

        try {
            const { data } = await api.post(`/company-policies/${policyId}/consent`, payload);

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
        consents = policyResponse.data.data?.consents || [];

        if (!policy) {
            throw new Error('Policy not found.');
        }

        render();
    } catch (error) {
        metaEl.innerHTML = `<div class="text-danger text-center py-4">${escapeHtml(getErrorMessage(error))}</div>`;
        showAlert(getErrorMessage(error), 'danger');
    }
});
