import { Modal } from 'bootstrap';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const postJson = async (url, body = {}) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = data?.message
            || (data?.errors ? Object.values(data.errors).flat()[0] : null)
            || 'Something went wrong.';
        throw new Error(message);
    }

    return data;
};

const postForm = async (url, formData) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = data?.message
            || (data?.errors ? Object.values(data.errors).flat()[0] : null)
            || 'Something went wrong.';
        throw new Error(message);
    }

    return data;
};

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('offerReviewRoot');
    if (!root || root.dataset.canRespond !== '1') {
        return;
    }

    const token = root.dataset.token;
    const alertBox = document.getElementById('offerReviewAlert');
    const canvas = document.getElementById('offerSignatureCanvas');
    const signatureFileInput = document.getElementById('offerSignatureFile');
    const signaturePreview = document.getElementById('offerSignaturePreview');
    const otpSection = document.getElementById('offerOtpSection');
    const otpInput = document.getElementById('offerOtpInput');
    const acceptBtn = document.getElementById('offerAcceptBtn');
    const declineModal = Modal.getOrCreateInstance(document.getElementById('offerDeclineModal'));

    let drawing = false;
    let ctx = null;
    let otpRequested = false;
    let otpVerified = false;
    let activeSignatureMode = 'draw';

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const setupCanvas = () => {
        if (!canvas) {
            return;
        }

        ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#111';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.floor(rect.width);
        canvas.height = 160;

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
    };

    const clearCanvas = () => {
        if (!canvas || !ctx) {
            return;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    const canvasHasInk = () => {
        if (!canvas || !ctx) {
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

    document.getElementById('offerSignatureClearBtn')?.addEventListener('click', clearCanvas);

    document.querySelectorAll('#offerSignatureTabs [data-bs-toggle="tab"]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', (event) => {
            activeSignatureMode = event.target.id === 'offer-upload-tab' ? 'upload' : 'draw';
        });
    });

    signatureFileInput?.addEventListener('change', () => {
        const file = signatureFileInput.files?.[0];
        if (!file || !signaturePreview) {
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            signaturePreview.src = reader.result;
            signaturePreview.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    });

    const requestOtp = async () => {
        await postJson(`/offer/${token}/request-otp`);
        otpRequested = true;
        otpSection?.classList.remove('d-none');
        acceptBtn.textContent = 'Verify & Accept Offer';
        showAlert('Verification code sent to your email.', 'success');
    };

    document.getElementById('offerResendOtpBtn')?.addEventListener('click', async () => {
        try {
            await requestOtp();
        } catch (error) {
            showAlert(error.message);
        }
    });

    acceptBtn?.addEventListener('click', async () => {
        const signatureName = document.getElementById('offerSignatureName')?.value.trim();
        if (!signatureName) {
            showAlert('Please enter your full name.');
            return;
        }

        if (activeSignatureMode === 'draw' && !canvasHasInk()) {
            showAlert('Please draw your signature or switch to upload.');
            return;
        }

        if (activeSignatureMode === 'upload' && !signatureFileInput?.files?.[0]) {
            showAlert('Please upload your signature image.');
            return;
        }

        acceptBtn.disabled = true;

        try {
            if (!otpRequested) {
                await requestOtp();
                acceptBtn.disabled = false;
                return;
            }

            const otp = otpInput?.value.trim() ?? '';
            if (!otpVerified) {
                if (otp.length !== 6) {
                    showAlert('Enter the 6-digit verification code from your email.');
                    acceptBtn.disabled = false;
                    return;
                }

                await postJson(`/offer/${token}/verify-otp`, { otp });
                otpVerified = true;
            }

            if (activeSignatureMode === 'upload' && signatureFileInput?.files?.[0]) {
                const formData = new FormData();
                formData.append('signature_name', signatureName);
                formData.append('signature_file', signatureFileInput.files[0]);
                await postForm(`/offer/${token}/accept`, formData);
            } else {
                await postJson(`/offer/${token}/accept`, {
                    signature_name: signatureName,
                    signature_data_url: canvas.toDataURL('image/png'),
                });
            }

            window.location.reload();
        } catch (error) {
            showAlert(error.message);
            acceptBtn.disabled = false;
        }
    });

    document.getElementById('offerDeclineBtn')?.addEventListener('click', () => {
        declineModal.show();
    });

    document.getElementById('offerDeclineConfirmBtn')?.addEventListener('click', async () => {
        const reason = document.getElementById('offerDeclineReason')?.value.trim() ?? '';

        try {
            await postJson(`/offer/${token}/decline`, { reason });
            window.location.reload();
        } catch (error) {
            showAlert(error.message);
        }
    });

    setupCanvas();
});
