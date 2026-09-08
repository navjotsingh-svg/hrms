import api, { getErrorMessage } from './api';
import { renderDateTimeStackFromLabel } from './datetime-utils';

const statusClass = (status) => ({
    draft: 'company-status-pill--inactive',
    pending_signature: 'company-status-pill--warning',
    signed: 'company-status-pill--active',
    declined: 'company-status-pill--cancelled',
    cancelled: 'company-status-pill--cancelled',
}[status] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('docLetterShowRoot');
    if (!root) return;

    const letterId = root.dataset.letterId;
    const canManage = root.dataset.canManage === '1';
    const alertBox = document.getElementById('docLetterShowAlert');
    const canvas = document.getElementById('signatureCanvas');
    const signatureCard = document.getElementById('docLetterSignatureCard');
    const signedCard = document.getElementById('docLetterSignedCard');
    const manageCard = document.getElementById('docLetterManageCard');
    const declineModal = window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('docLetterDeclineModal'));

    let letter = null;
    let drawing = false;
    let ctx = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const setupCanvas = () => {
        if (!canvas) return;
        ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#111';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.floor(rect.width);
        canvas.height = 180;

        const getPos = (event) => {
            const bounds = canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;
            return {
                x: source.clientX - bounds.left,
                y: source.clientY - bounds.top,
            };
        };

        const start = (event) => {
            drawing = true;
            const pos = getPos(event);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            event.preventDefault();
        };

        const draw = (event) => {
            if (!drawing) return;
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
    };

    const clearCanvas = () => {
        if (!canvas || !ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    const canvasHasInk = () => {
        if (!canvas || !ctx) return false;
        const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        for (let i = 3; i < data.length; i += 4) {
            if (data[i] > 0) return true;
        }
        return false;
    };

    const renderLetter = () => {
        if (!letter) return;

        document.getElementById('docLetterShowTitle').textContent = letter.title;
        document.getElementById('docLetterShowSubtitle').textContent = letter.document_number;

        document.getElementById('docLetterMeta').innerHTML = `
            <div class="row g-3">
                <div class="col-md-3"><div class="small text-muted">Status</div><span class="company-status-pill ${statusClass(letter.status)}">${letter.status_label}</span></div>
                <div class="col-md-3"><div class="small text-muted">Category</div><div>${letter.category_label}</div></div>
                <div class="col-md-3"><div class="small text-muted">Employee</div><div>${letter.employee?.full_name || '—'}</div></div>
                <div class="col-md-3"><div class="small text-muted">Issued</div><div>${renderDateTimeStackFromLabel(letter.issued_at_label)}</div></div>
            </div>
        `;

        document.getElementById('docLetterContent').innerHTML = letter.rendered_html || '';

        signatureCard?.classList.toggle('d-none', !letter.can_sign);
        signedCard?.classList.toggle('d-none', !['signed', 'declined'].includes(letter.status));
        manageCard?.classList.toggle('d-none', !canManage);

        if (letter.status === 'signed') {
            document.getElementById('docLetterSignedDetails').innerHTML = `
                <p class="mb-2"><strong>Signed by:</strong> ${letter.signature_name || letter.signed_by?.name || '—'}</p>
                <p class="mb-2"><strong>Signed on:</strong> ${renderDateTimeStackFromLabel(letter.signed_at_label)}</p>
                ${letter.signature_image_url ? `<img src="${letter.signature_image_url}" alt="Signature" class="border rounded bg-white" style="max-width: 320px;">` : ''}
            `;
        }

        if (letter.status === 'declined') {
            document.getElementById('docLetterSignedDetails').innerHTML = `
                <p class="mb-0 text-danger"><strong>Declined:</strong> ${letter.decline_reason || 'No reason provided.'}</p>
            `;
        }

        document.getElementById('docLetterIssueBtn')?.classList.toggle('d-none', letter.status !== 'draft');
        document.getElementById('docLetterCancelBtn')?.classList.toggle('d-none', !['draft', 'pending_signature', 'declined'].includes(letter.status));
    };

    const loadLetter = async () => {
        const { data } = await api.get(`/document-letters/${letterId}`);
        letter = data.data.letter;
        renderLetter();
    };

    document.getElementById('signatureClearBtn')?.addEventListener('click', clearCanvas);

    document.getElementById('docLetterSignForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const signatureName = document.getElementById('signatureName').value.trim();
        if (!signatureName) {
            showAlert('Please enter your full name.', 'danger');
            return;
        }

        const payload = { signature_name: signatureName };
        if (canvasHasInk()) {
            payload.signature_data_url = canvas.toDataURL('image/png');
        }

        try {
            const { data } = await api.post(`/document-letters/${letterId}/sign`, payload);
            letter = data.data.letter;
            showAlert('Document signed successfully.');
            renderLetter();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('docLetterDeclineBtn')?.addEventListener('click', () => {
        document.getElementById('declineReason').value = '';
        declineModal?.show();
    });

    document.getElementById('docLetterDeclineForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const { data } = await api.patch(`/document-letters/${letterId}/decline`, {
                reason: document.getElementById('declineReason').value.trim(),
            });
            letter = data.data.letter;
            declineModal?.hide();
            showAlert('Document declined.');
            renderLetter();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('docLetterIssueBtn')?.addEventListener('click', async () => {
        try {
            const { data } = await api.patch(`/document-letters/${letterId}/issue`);
            letter = data.data.letter;
            showAlert('Document issued to employee.');
            renderLetter();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('docLetterCancelBtn')?.addEventListener('click', async () => {
        if (!window.confirm('Cancel this document?')) return;
        try {
            const { data } = await api.patch(`/document-letters/${letterId}/cancel`);
            letter = data.data.letter;
            showAlert('Document cancelled.');
            renderLetter();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    setupCanvas();

    try {
        await loadLetter();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
