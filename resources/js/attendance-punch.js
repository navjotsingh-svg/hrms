import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';
import { describeCameraError, describeLocationError, formatCoordinates, getPositionWithFallback, reverseGeocode } from './location-utils';
import { getDeviceMacAddress } from './device-utils';

const loadFaceVerification = () => import('./face-verification');

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
    const modalFaceStatus = document.getElementById(`${prefix}ModalFaceStatus`);
    const modalIpStatus = document.getElementById(`${prefix}ModalIpStatus`);
    const modalHint = document.getElementById(`${prefix}ModalHint`);
    const captureBtn = document.getElementById(`${prefix}CaptureBtn`);
    const liveMatchOverlay = document.getElementById(`${prefix}LiveMatchOverlay`);
    const liveMatchValue = document.getElementById(`${prefix}LiveMatchValue`);
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
    let profilePhotoUrl = null;
    let faceMatchThreshold = 80;
    let requireFaceMatch = true;
    let hasProfilePhoto = false;
    let clientIpAddress = null;
    let clientMacAddress = null;
    let livePreviewRunning = false;
    let livePreviewTimer = null;
    let livePreviewBusy = false;

    const LIVE_PREVIEW_INTERVAL_MS = 900;

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
        stopLivePreview();
        cameraStream?.getTracks().forEach((track) => track.stop());
        cameraStream = null;
        cameraVideo.srcObject = null;
        cameraVideo.classList.add('d-none');
        cameraPlaceholder?.classList.remove('d-none');
        if (cameraPlaceholder) {
            cameraPlaceholder.querySelector('span').textContent = 'Opening camera...';
        }
    };

    const showCameraMessage = (message) => {
        cameraVideo.classList.add('d-none');
        cameraPlaceholder?.classList.remove('d-none');

        if (cameraPlaceholder) {
            cameraPlaceholder.querySelector('span').textContent = message;
        }
    };

    const waitForVideoReady = (video) => new Promise((resolve) => {
        if (video.readyState >= 2 && video.videoWidth > 0) {
            resolve();
            return;
        }

        const done = () => {
            video.removeEventListener('loadeddata', done);
            video.removeEventListener('loadedmetadata', done);
            resolve();
        };

        video.addEventListener('loadeddata', done, { once: true });
        video.addEventListener('loadedmetadata', done, { once: true });
    });

    const updatePanel = (status) => {
        nextPunchType = status.next_punch_type;
        canMark = Boolean(status.can_mark);

        if (!canMark) {
            nextActionPill.textContent = status.day_message || status.status_label || 'Attendance marking is unavailable today.';
            nextActionPill.className = 'attendance-status-pill';
            punchBtn.disabled = true;
            punchBtn.textContent = 'Mark Attendance';
        } else if (status.requires_profile_photo !== false && !status.has_profile_photo) {
            nextActionPill.textContent = 'Upload an approved profile photo before marking attendance.';
            nextActionPill.className = 'attendance-status-pill';
            punchBtn.disabled = true;
            punchBtn.textContent = 'Profile Photo Required';
        } else {
            const label = actionLabel();
            nextActionPill.textContent = label;
            nextActionPill.className = `attendance-status-pill ${nextPunchType === 'out' ? 'attendance-status-pill--out' : 'attendance-status-pill--in'}`;
            punchBtn.textContent = label;
            punchBtn.disabled = isSubmitting;
        }

        if (todaySummary) {
            if (status.awaiting_punch_out) {
                const punchInLabel = status.current_punch_in_label || status.punch_in_label || '—';
                const expectedOut = status.expected_clock_out_label
                    ? `<span class="attendance-punch-stat attendance-punch-stat--expected-out">Expected clock out time for full day present ${status.expected_clock_out_label}</span>`
                    : '';

                todaySummary.innerHTML = `
                    <span class="attendance-punch-stat">In ${punchInLabel}</span>
                    ${expectedOut}
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

        profilePhotoUrl = status.profile_photo_url || null;
        faceMatchThreshold = Number(status.face_match_threshold) || 80;
        requireFaceMatch = status.require_face_match !== false;
        hasProfilePhoto = Boolean(status.has_profile_photo);

        if (modalHint) {
            modalHint.textContent = requireFaceMatch
                ? `Hold the device at arm's length. Watch the live match % on the camera and adjust until it stays at or above ${faceMatchThreshold}%.`
                : 'Take a clear photo to mark your attendance. Face recognition is disabled for your company.';
        }

        modalFaceStatus?.classList.toggle('d-none', !requireFaceMatch);
        if (!requireFaceMatch) {
            liveMatchOverlay?.classList.add('d-none');
        }

        onStatus?.(status);
    };

    const syncFaceReference = async () => {
        if (!requireFaceMatch || !profilePhotoUrl) {
            return;
        }

        try {
            const { ensureFaceModelsLoaded, getProfileDescriptor, descriptorToArray } = await loadFaceVerification();
            await ensureFaceModelsLoaded();
            const descriptor = await getProfileDescriptor(profilePhotoUrl);
            await api.post('/attendance/face-reference', {
                descriptor: descriptorToArray(descriptor),
            });
        } catch {
            // Face reference sync is retried on the next status refresh.
        }
    };

    const loadClientNetwork = async () => {
        const { getDeviceMacAddress } = await import('./device-utils');

        try {
            const { data } = await api.get('/attendance/current-ip');
            clientIpAddress = data.data?.ip_address || null;
        } catch {
            clientIpAddress = null;
        }

        clientMacAddress = await getDeviceMacAddress();

        if (modalIpStatus) {
            const ipText = clientIpAddress
                ? `Your IPv4: ${clientIpAddress}`
                : 'Your IPv4 address will be recorded with this punch.';
            const macText = clientMacAddress
                ? ` · MAC address: ${clientMacAddress}`
                : ' · Device MAC address will be recorded with this punch.';

            modalIpStatus.textContent = `${ipText}${macText}`;
        }
    };

    const setFaceStatus = (message, type = 'muted') => {
        if (!modalFaceStatus) {
            return;
        }

        modalFaceStatus.className = `small attendance-face-status attendance-face-status--${type}`;
        modalFaceStatus.textContent = message;
    };

    const updateLiveMatchOverlay = (similarity) => {
        if (!liveMatchOverlay || !liveMatchValue) {
            return;
        }

        if (similarity === null || similarity === undefined) {
            liveMatchOverlay.classList.remove('d-none', 'attendance-live-match--ready', 'attendance-live-match--low');
            liveMatchOverlay.classList.add('attendance-live-match--none');
            liveMatchValue.textContent = '—%';
            return;
        }

        liveMatchOverlay.classList.remove('d-none', 'attendance-live-match--none');
        liveMatchValue.textContent = `${similarity}%`;

        const meetsTarget = similarity >= faceMatchThreshold;

        liveMatchOverlay.classList.toggle('attendance-live-match--ready', meetsTarget);
        liveMatchOverlay.classList.toggle('attendance-live-match--low', !meetsTarget);
    };

    const stopLivePreview = () => {
        livePreviewRunning = false;
        livePreviewBusy = false;

        if (livePreviewTimer) {
            clearTimeout(livePreviewTimer);
            livePreviewTimer = null;
        }

        liveMatchOverlay?.classList.add('d-none');
    };

    const scheduleLivePreview = () => {
        if (!livePreviewRunning) {
            return;
        }

        livePreviewTimer = setTimeout(runLivePreviewTick, LIVE_PREVIEW_INTERVAL_MS);
    };

    const runLivePreviewTick = async () => {
        if (!livePreviewRunning || isSubmitting || livePreviewBusy || !profilePhotoUrl) {
            scheduleLivePreview();
            return;
        }

        if (!cameraVideo.videoWidth || cameraVideo.classList.contains('d-none')) {
            scheduleLivePreview();
            return;
        }

        livePreviewBusy = true;

        try {
            const { previewMatchFromVideo } = await loadFaceVerification();
            const result = await previewMatchFromVideo({
                profilePhotoUrl,
                videoElement: cameraVideo,
                threshold: faceMatchThreshold,
            });

            if (!livePreviewRunning) {
                return;
            }

            if (result.detected) {
                updateLiveMatchOverlay(result.similarity);

                if (requireFaceMatch) {
                    setFaceStatus(
                        result.matched
                            ? `Live match ${result.similarity}% — ready to punch (need ${faceMatchThreshold}%). Adjust distance until stable.`
                            : `Live match ${result.similarity}% — need ${faceMatchThreshold}%. Move back slightly and center your face.`,
                        result.matched ? 'success' : 'warning',
                    );
                } else {
                    setFaceStatus(
                        `Live match ${result.similarity}% (target ${faceMatchThreshold}%). Adjust position until the score is stable.`,
                        result.matched ? 'success' : 'muted',
                    );
                }
            } else {
                updateLiveMatchOverlay(null);
                setFaceStatus('No face detected — look at the camera and keep your face in the frame.', 'warning');
            }
        } catch {
            if (livePreviewRunning) {
                updateLiveMatchOverlay(null);
            }
        } finally {
            livePreviewBusy = false;
            scheduleLivePreview();
        }
    };

    const startLivePreview = () => {
        if (!requireFaceMatch) {
            return;
        }

        stopLivePreview();
        livePreviewRunning = true;
        liveMatchOverlay?.classList.remove('d-none');
        updateLiveMatchOverlay(null);
        runLivePreviewTick();
    };

    const startCamera = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera is not supported in this browser.');
        }

        const constraintAttempts = [
            { video: { facingMode: 'user' }, audio: false },
            { video: { facingMode: { ideal: 'user' } }, audio: false },
            { video: { width: { ideal: 640 }, height: { ideal: 480 } }, audio: false },
            { video: true, audio: false },
        ];

        let lastError = null;

        for (const constraints of constraintAttempts) {
            try {
                stopCamera();
                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                cameraVideo.srcObject = cameraStream;
                cameraVideo.muted = true;
                cameraVideo.setAttribute('playsinline', 'true');
                cameraVideo.setAttribute('webkit-playsinline', 'true');
                cameraPlaceholder?.classList.add('d-none');
                cameraVideo.classList.remove('d-none');
                await cameraVideo.play();
                await waitForVideoReady(cameraVideo);

                if (!cameraVideo.videoWidth) {
                    throw new Error('Camera preview did not start.');
                }

                return;
            } catch (error) {
                lastError = error;
                stopCamera();
            }
        }

        const message = describeCameraError(lastError)
            || (lastError instanceof Error ? lastError.message : null)
            || 'Unable to access the camera. Please allow camera permission and try again.';

        throw new Error(message);
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
        if (modalIpStatus) {
            modalIpStatus.textContent = 'Checking network IP and MAC address...';
        }
        setFaceStatus(
            requireFaceMatch
                ? 'Face verification uses your approved profile photo.'
                : 'Take a clear photo to save your punch.',
            'muted',
        );
        stopLivePreview();
        stopCamera();
    };

    const openPunchModal = async () => {
        if (!canMark || nextPunchType === null || isSubmitting) {
            return;
        }

        if (requireFaceMatch && !hasProfilePhoto) {
            showAlert('Upload and get an approved profile photo before marking attendance.', 'warning');
            return;
        }

        resetModalState();

        const label = actionLabel();
        if (punchModalTitle) {
            punchModalTitle.textContent = label;
        }
        captureBtn.textContent = `Take Photo & ${label}`;

        punchModal.show();

        loadClientNetwork();

        if (requireFaceMatch) {
            try {
                setFaceStatus('Loading face verification models...', 'muted');
                const { ensureFaceModelsLoaded } = await loadFaceVerification();
                await ensureFaceModelsLoaded();
                await syncFaceReference();
                setFaceStatus('Starting live face match preview...', 'muted');
            } catch (error) {
                setFaceStatus(getErrorMessage(error, 'Face verification is unavailable right now.'), 'danger');
                captureBtn.disabled = true;
            }
        } else {
            liveMatchOverlay?.classList.add('d-none');
        }

        try {
            await startCamera();
            startLivePreview();
        } catch (error) {
            showCameraMessage(getErrorMessage(error, 'Unable to access the camera. Please allow camera permission and try again.'));
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
        stopLivePreview();

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

            if (requireFaceMatch) {
                setFaceStatus('Verifying face against your profile photo...', 'muted');
                const { verifySelfieAgainstProfile, descriptorToArray } = await loadFaceVerification();
                const faceResult = await verifySelfieAgainstProfile({
                    profilePhotoUrl,
                    videoElement: cameraVideo,
                    threshold: faceMatchThreshold,
                });

                if (!faceResult.matched) {
                    throw new Error(`Face match ${faceResult.similarity}% — at least ${faceMatchThreshold}% is required.`);
                }

                setFaceStatus(`Face verified (${faceResult.similarity}% match). Saving punch...`, 'success');
                formData.append('face_match_score', String(faceResult.similarity));

                descriptorToArray(faceResult.selfieDescriptor).forEach((value, index) => {
                    formData.append(`selfie_face_descriptor[${index}]`, String(value));
                });
            } else {
                setFaceStatus('Saving punch...', 'muted');
            }

            const macAddress = await getDeviceMacAddress();

            if (macAddress) {
                formData.append('mac_address', macAddress);
            }

            const { data } = await api.post('/attendance/punch', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            punchModal.hide();
            showAlert(data.message || 'Attendance marked successfully.');
            onPunched?.(data.data);
            await refreshStatus();
        } catch (error) {
            const message = describeCameraError(error)
                || describeLocationError(error)
                || getErrorMessage(error, error?.message || 'Unable to save attendance punch.');

            if (describeCameraError(error)) {
                showCameraMessage(message);
            } else if (modalFaceStatus && (error?.message || '').toLowerCase().includes('face')) {
                setFaceStatus(message, 'danger');
            } else if (modalLocationStatus) {
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
                if (punchModalEl.classList.contains('show') && cameraStream) {
                    startLivePreview();
                }
            }

            prefetchLocation();
        }
    };

    const refreshStatus = async () => {
        const { data } = await api.get('/attendance/status');
        updatePanel(data.data);

        if (requireFaceMatch) {
            await syncFaceReference();
        }

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
