import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';
import { describeLocationError, formatCoordinates, getPositionWithFallback, reverseGeocode } from './location-utils';

export function initAttendancePunch({
    prefix,
    onPunched,
    onStatus,
    alertElementId,
}) {
    const punchBtn = document.getElementById(`${prefix}PunchBtn`);
    const nextActionPill = document.getElementById(`${prefix}NextActionPill`);
    const locationStatus = document.getElementById(`${prefix}LocationStatus`);
    const todaySummary = document.getElementById(`${prefix}TodaySummary`);
    const cameraVideo = document.getElementById(`${prefix}Camera`);
    const cameraCanvas = document.getElementById(`${prefix}CameraCanvas`);
    const cameraPlaceholder = document.getElementById(`${prefix}CameraPlaceholder`);
    const punchModalEl = document.getElementById(`${prefix}PunchModal`);
    const punchModalTitle = document.getElementById(`${prefix}PunchModalTitle`);
    const modalLocationStatus = document.getElementById(`${prefix}ModalLocationStatus`);
    const captureBtn = document.getElementById(`${prefix}CaptureBtn`);
    const alertBox = alertElementId ? document.getElementById(alertElementId) : null;

    if (!punchBtn || !cameraVideo || !cameraCanvas || !punchModalEl || !captureBtn) {
        return { refreshStatus: async () => {}, destroy: () => {} };
    }

    const punchModal = Modal.getOrCreateInstance(punchModalEl);

    let cameraStream = null;
    let nextPunchType = null;
    let canMark = false;
    let isSubmitting = false;
    let cachedPosition = null;
    let cachedLocationName = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const actionLabel = () => (nextPunchType === 'out' ? 'Punch Out' : 'Punch In');

    const stopCamera = () => {
        cameraStream?.getTracks().forEach((track) => track.stop());
        cameraStream = null;
        cameraVideo.srcObject = null;
        cameraVideo.classList.add('d-none');
        cameraPlaceholder?.classList.remove('d-none');
        if (cameraPlaceholder) {
            cameraPlaceholder.querySelector('span').textContent = 'Opening camera...';
        }
    };

    const updatePanel = (status) => {
        nextPunchType = status.next_punch_type;
        canMark = Boolean(status.can_mark);

        if (!canMark) {
            nextActionPill.textContent = status.day_message || status.status_label || 'Attendance marking is unavailable today.';
            nextActionPill.className = 'attendance-status-pill';
            punchBtn.disabled = true;
            punchBtn.textContent = 'Mark Attendance';
        } else {
            const label = actionLabel();
            nextActionPill.textContent = label;
            nextActionPill.className = `attendance-status-pill ${nextPunchType === 'out' ? 'attendance-status-pill--out' : 'attendance-status-pill--in'}`;
            punchBtn.textContent = label;
            punchBtn.disabled = isSubmitting;
        }

        if (todaySummary) {
            if (status.awaiting_punch_out) {
                todaySummary.innerHTML = `
                    <span class="attendance-punch-stat">In ${status.punch_in_label || '—'}</span>
                `;
            } else {
                const worked = status.today_worked_minutes || 0;
                const required = status.required_minutes || 0;
                const statusClass = status.is_complete ? 'text-success' : 'text-warning';
                todaySummary.innerHTML = `
                    <span class="attendance-punch-stat"><strong>${Math.floor(worked / 60)}h ${worked % 60}m</strong> worked</span>
                    <span class="attendance-punch-stat">Req ${Math.floor(required / 60)}h ${required % 60}m</span>
                    <span class="attendance-punch-stat">In ${status.punch_in_label || '—'}</span>
                    <span class="attendance-punch-stat">Out ${status.punch_out_label || '—'}</span>
                    <span class="attendance-punch-stat ${statusClass}">${status.status_label || 'In progress'}</span>
                `;
            }
        }

        onStatus?.(status);
    };

    const startCamera = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera is not supported on this device.');
        }

        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: false,
        });

        cameraVideo.srcObject = cameraStream;
        cameraPlaceholder?.classList.add('d-none');
        cameraVideo.classList.remove('d-none');

        await new Promise((resolve) => {
            if (cameraVideo.readyState >= 2) {
                resolve();
                return;
            }

            cameraVideo.onloadeddata = () => resolve();
        });
    };

    const captureSelfieBlob = () => new Promise((resolve, reject) => {
        const sourceWidth = cameraVideo.videoWidth || 640;
        const sourceHeight = cameraVideo.videoHeight || 480;
        const maxWidth = 480;
        let width = sourceWidth;
        let height = sourceHeight;

        if (width > maxWidth) {
            height = Math.round((height / width) * maxWidth);
            width = maxWidth;
        }

        cameraCanvas.width = width;
        cameraCanvas.height = height;
        const context = cameraCanvas.getContext('2d');

        if (!context) {
            reject(new Error('Unable to capture selfie.'));
            return;
        }

        context.drawImage(cameraVideo, 0, 0, width, height);
        cameraCanvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('Unable to capture selfie.'));
                return;
            }

            resolve(blob);
        }, 'image/jpeg', 0.72);
    });

    const resolveLocationName = async (position) => {
        const { latitude, longitude } = position.coords;
        const locationName = await reverseGeocode(latitude, longitude);

        return locationName || formatCoordinates(latitude, longitude);
    };

    const prefetchLocation = async () => {
        if (!locationStatus) {
            return;
        }

        locationStatus.textContent = 'Fetching location...';

        try {
            const position = await getPositionWithFallback();
            const locationName = await resolveLocationName(position);
            locationStatus.textContent = locationName;
        } catch {
            locationStatus.textContent = 'Location captured at punch time.';
        }
    };

    const resetModalState = () => {
        cachedPosition = null;
        cachedLocationName = null;
        captureBtn.disabled = true;
        captureBtn.textContent = 'Take Photo & Punch';
        if (modalLocationStatus) {
            modalLocationStatus.textContent = 'Getting location...';
        }
        stopCamera();
    };

    const openPunchModal = async () => {
        if (!canMark || nextPunchType === null || isSubmitting) {
            return;
        }

        resetModalState();

        const label = actionLabel();
        if (punchModalTitle) {
            punchModalTitle.textContent = label;
        }
        captureBtn.textContent = `Take Photo & ${label}`;

        punchModal.show();

        try {
            await startCamera();
        } catch (error) {
            if (modalLocationStatus) {
                modalLocationStatus.textContent = getErrorMessage(error, 'Unable to access the camera. Please allow camera permission and try again.');
            }
            captureBtn.disabled = true;
            return;
        }

        try {
            const position = await getPositionWithFallback();

            cachedPosition = position;
            cachedLocationName = await resolveLocationName(position);

            if (modalLocationStatus) {
                modalLocationStatus.textContent = cachedLocationName;
            }
        } catch (error) {
            // Location will be retried when the punch is submitted.
            if (modalLocationStatus) {
                modalLocationStatus.textContent = describeLocationError(error)
                    || getErrorMessage(error, 'Could not fetch location. It will be retried when you punch.');
            }
        }

        captureBtn.disabled = false;
    };

    const submitPunch = async () => {
        if (!canMark || nextPunchType === null || isSubmitting) {
            return;
        }

        isSubmitting = true;
        punchBtn.disabled = true;
        captureBtn.disabled = true;

        try {
            setSubmitLoading(captureBtn, true, { submittingText: 'Saving...' });

            const position = cachedPosition || await getPositionWithFallback();
            const locationName = cachedLocationName || await resolveLocationName(position);
            const selfieBlob = await captureSelfieBlob();
            const formData = new FormData();
            formData.append('selfie', selfieBlob, 'selfie.jpg');
            formData.append('latitude', String(position.coords.latitude));
            formData.append('longitude', String(position.coords.longitude));
            formData.append('location_name', locationName);

            const { data } = await api.post('/attendance/punch', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            punchModal.hide();
            showAlert(data.message || 'Attendance marked successfully.');
            onPunched?.(data.data);
            await refreshStatus();
        } catch (error) {
            const message = describeLocationError(error) || getErrorMessage(error);

            if (modalLocationStatus) {
                modalLocationStatus.textContent = message;
            }
            showAlert(message, 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(captureBtn, false);
            captureBtn.textContent = `Take Photo & ${actionLabel()}`;

            if (canMark && nextPunchType !== null) {
                punchBtn.textContent = actionLabel();
                punchBtn.disabled = false;
                captureBtn.disabled = false;
            }

            prefetchLocation();
        }
    };

    const refreshStatus = async () => {
        const { data } = await api.get('/attendance/status');
        updatePanel(data.data);
        return data.data;
    };

    const destroy = () => {
        stopCamera();
        punchModal.hide();
    };

    punchBtn.addEventListener('click', openPunchModal);
    captureBtn.addEventListener('click', submitPunch);
    punchModalEl.addEventListener('hidden.bs.modal', resetModalState);
    prefetchLocation();
    refreshStatus().catch((error) => {
        if (nextActionPill) {
            nextActionPill.textContent = getErrorMessage(error, 'Unable to load attendance status.');
        }

        punchBtn.disabled = true;
    });

    return { refreshStatus, destroy };
}
