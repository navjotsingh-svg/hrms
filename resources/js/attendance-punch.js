import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';
import { describeCameraError, describeLocationError, formatCoordinates, getPositionWithFallback, reverseGeocode } from './location-utils';
import { getDeviceMacAddress } from './device-utils';

const loadFaceVerification = () => import('./face-verification');

const buildMapEmbedUrl = (latitude, longitude) => {
    const lat = Number(latitude);
    const lng = Number(longitude);
    const delta = 0.012;
    const west = (lng - delta).toFixed(6);
    const south = (lat - delta).toFixed(6);
    const east = (lng + delta).toFixed(6);
    const north = (lat + delta).toFixed(6);

    return `https://www.openstreetmap.org/export/embed.html?bbox=${west}%2C${south}%2C${east}%2C${north}&layer=mapnik&marker=${lat}%2C${lng}`;
};

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
    const punchModalLayout = document.getElementById(`${prefix}PunchModalLayout`);
    const punchModalTitle = document.getElementById(`${prefix}PunchModalTitle`);
    const modalLocationStatus = document.getElementById(`${prefix}ModalLocationStatus`);
    const modalLocationCoords = document.getElementById(`${prefix}ModalLocationCoords`);
    const modalLocationBadge = document.getElementById(`${prefix}ModalLocationBadge`);
    const modalLocationMap = document.getElementById(`${prefix}ModalLocationMap`);
    const modalLocationMapLoading = document.getElementById(`${prefix}ModalLocationMapLoading`);
    const captureBtn = document.getElementById(`${prefix}CaptureBtn`);
    const liveMatchOverlay = document.getElementById(`${prefix}LiveMatchOverlay`);
    const liveMatchValue = document.getElementById(`${prefix}LiveMatchValue`);
    const alertBox = alertElementId ? document.getElementById(alertElementId) : null;

    if (!punchBtn || !cameraVideo || !cameraCanvas || !punchModalEl || !captureBtn) {
        return { refreshStatus: async () => {}, destroy: () => {} };
    }

    if (punchModalEl.parentElement !== document.body) {
        document.body.appendChild(punchModalEl);
    }

    const punchModal = Modal.getOrCreateInstance(punchModalEl);

    let cameraStream = null;
    let nextPunchType = null;
    let canMark = false;
    let isSubmitting = false;
    let cachedPosition = null;
    let cachedLocationName = null;
    let profilePhotoUrl = null;
    let faceMatchThreshold = 90;
    let requireFaceMatch = true;
    let requirePunchPhoto = true;
    let hasProfilePhoto = false;
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

    const captureBtnLabel = () => (
        requirePunchPhoto ? `Take Photo & ${actionLabel()}` : actionLabel()
    );

    const setLocationOnlyMode = (enabled) => {
        punchModalLayout?.classList.toggle('attendance-punch-modal-layout--location-only', enabled);
    };

    const updateLocationPanel = ({
        latitude = null,
        longitude = null,
        locationName = '',
        state = 'loading',
    } = {}) => {
        const isReady = state === 'ready' && latitude != null && longitude != null;
        const isError = state === 'error';

        if (modalLocationBadge) {
            modalLocationBadge.textContent = isReady ? 'Confirmed' : isError ? 'Unavailable' : 'Detecting';
            modalLocationBadge.className = `attendance-punch-location-badge${
                isReady ? ' attendance-punch-location-badge--ready' : isError ? ' attendance-punch-location-badge--error' : ''
            }`;
        }

        if (modalLocationStatus) {
            modalLocationStatus.textContent = locationName
                || (isError ? 'Could not detect location' : 'Getting location…');
        }

        if (modalLocationCoords) {
            modalLocationCoords.textContent = isReady
                ? formatCoordinates(latitude, longitude)
                : '';
        }

        if (!modalLocationMap || !modalLocationMapLoading) {
            return;
        }

        if (isReady) {
            modalLocationMapLoading.classList.add('d-none');
            modalLocationMap.onload = () => {
                modalLocationMap.classList.remove('d-none');
            };
            modalLocationMap.onerror = () => {
                modalLocationMap.classList.add('d-none');
                modalLocationMapLoading.innerHTML = '<span>Map preview unavailable</span>';
                modalLocationMapLoading.classList.remove('d-none');
            };
            modalLocationMap.src = buildMapEmbedUrl(latitude, longitude);
            window.setTimeout(() => {
                if (modalLocationMap.src && modalLocationMap.classList.contains('d-none')) {
                    modalLocationMap.classList.remove('d-none');
                    modalLocationMapLoading.classList.add('d-none');
                }
            }, 1200);
            return;
        }

        modalLocationMap.classList.add('d-none');
        modalLocationMap.src = 'about:blank';
        modalLocationMapLoading.classList.remove('d-none');

        if (isError) {
            modalLocationMapLoading.innerHTML = `<span>${locationName || 'Location unavailable'}</span>`;
            return;
        }

        modalLocationMapLoading.innerHTML = `
            <span class="attendance-punch-location-map-spinner" aria-hidden="true"></span>
            <span>Getting your location…</span>
        `;
    };

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
                const expectedOutLabel = status.leave_session_label && status.leave_session_label !== 'Full Day'
                    ? `Expected clock out for remaining half ${status.expected_clock_out_label}`
                    : `Expected clock out time for full day present ${status.expected_clock_out_label}`;
                const expectedOut = status.expected_clock_out_label
                    ? `<span class="attendance-punch-stat attendance-punch-stat--expected-out">${expectedOutLabel}</span>`
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
        faceMatchThreshold = Number(status.face_match_threshold) || 90;
        requireFaceMatch = status.require_face_match !== false;
        requirePunchPhoto = status.require_punch_photo !== false;
        hasProfilePhoto = Boolean(status.has_profile_photo);

        loadFaceVerification().then(({ bindProfileFaceContext }) => {
            bindProfileFaceContext({
                photoUrl: profilePhotoUrl,
                descriptor: Array.isArray(status.profile_face_descriptor) && status.profile_face_descriptor.length >= 64
                    ? status.profile_face_descriptor
                    : null,
            });
        }).catch(() => {});

        if (!requirePunchPhoto || !requireFaceMatch) {
            liveMatchOverlay?.classList.add('d-none');
        }

        onStatus?.(status);
    };

    const syncFaceReference = async () => {
        if (!requirePunchPhoto || !requireFaceMatch || !profilePhotoUrl) {
            return;
        }

        try {
            const { ensureFaceModelsLoaded, getProfileDescriptor, descriptorToArray, bindProfileFaceContext } = await loadFaceVerification();
            await ensureFaceModelsLoaded();
            bindProfileFaceContext({ photoUrl: profilePhotoUrl });
            const descriptor = await getProfileDescriptor(profilePhotoUrl, { forceRefresh: true });
            bindProfileFaceContext({
                photoUrl: profilePhotoUrl,
                descriptor: descriptorToArray(descriptor),
            });
            await api.post('/attendance/face-reference', {
                descriptor: descriptorToArray(descriptor),
            });
        } catch {
            // Face reference sync is retried on the next status refresh.
        }
    };

    const loadClientNetwork = async () => {
        clientMacAddress = await getDeviceMacAddress();
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
            } else {
                updateLiveMatchOverlay(null);
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
        if (!requirePunchPhoto || !requireFaceMatch) {
            return;
        }

        stopLivePreview();
        loadFaceVerification().then(({ resetLiveMatchHistory }) => {
            resetLiveMatchHistory();
        }).catch(() => {});
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
        captureBtn.textContent = captureBtnLabel();
        setLocationOnlyMode(false);
        updateLocationPanel({ state: 'loading' });
        stopLivePreview();
        stopCamera();
    };

    const openPunchModal = async () => {
        if (!canMark || nextPunchType === null || isSubmitting) {
            return;
        }

        if (requirePunchPhoto && requireFaceMatch && !hasProfilePhoto) {
            showAlert('Upload and get an approved profile photo before marking attendance.', 'warning');
            return;
        }

        resetModalState();

        const label = actionLabel();
        if (punchModalTitle) {
            punchModalTitle.textContent = label;
        }
        captureBtn.textContent = captureBtnLabel();

        punchModal.show();

        loadClientNetwork();

        if (requirePunchPhoto && requireFaceMatch) {
            try {
                const { ensureFaceModelsLoaded } = await loadFaceVerification();
                await ensureFaceModelsLoaded();
                await syncFaceReference();
            } catch (error) {
                showAlert(getErrorMessage(error, 'Face verification unavailable.'), 'danger');
                captureBtn.disabled = true;
            }
        } else {
            liveMatchOverlay?.classList.add('d-none');
        }

        if (requirePunchPhoto) {
            try {
                await startCamera();
                startLivePreview();
            } catch (error) {
                showCameraMessage(getErrorMessage(error, 'Unable to access the camera. Please allow camera permission and try again.'));
                captureBtn.disabled = true;
                return;
            }
        } else {
            setLocationOnlyMode(true);
        }

        try {
            const position = await getPositionWithFallback();

            cachedPosition = position;
            cachedLocationName = await resolveLocationName(position);

            updateLocationPanel({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                locationName: cachedLocationName,
                state: 'ready',
            });
        } catch (error) {
            updateLocationPanel({
                locationName: describeLocationError(error) || 'Location will be captured on punch',
                state: 'error',
            });
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

        if (requirePunchPhoto) {
            stopLivePreview();
        }

        try {
            setSubmitLoading(captureBtn, true, { submittingText: 'Saving...' });

            const position = cachedPosition || await getPositionWithFallback();
            const locationName = cachedLocationName || await resolveLocationName(position);

            const formData = new FormData();
            formData.append('latitude', String(position.coords.latitude));
            formData.append('longitude', String(position.coords.longitude));
            formData.append('location_name', locationName);

            if (requirePunchPhoto) {
                const selfieBlob = await captureSelfieBlob();
                formData.append('selfie', selfieBlob, 'selfie.jpg');

                if (requireFaceMatch) {
                    const { verifySelfieAgainstProfile, descriptorToArray } = await loadFaceVerification();
                    const faceResult = await verifySelfieAgainstProfile({
                        profilePhotoUrl,
                        videoElement: cameraVideo,
                        threshold: faceMatchThreshold,
                    });

                    if (!faceResult.matched) {
                        throw new Error(`Face match ${faceResult.similarity}% — need ${faceMatchThreshold}%.`);
                    }

                    formData.append('face_match_score', String(faceResult.similarity));

                    descriptorToArray(faceResult.selfieDescriptor).forEach((value, index) => {
                        formData.append(`selfie_face_descriptor[${index}]`, String(value));
                    });
                }
            }

            const macAddress = clientMacAddress || await getDeviceMacAddress();

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
            } else if (describeLocationError(error)) {
                updateLocationPanel({
                    locationName: message,
                    state: 'error',
                });
            }

            showAlert(message, 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(captureBtn, false);
            captureBtn.textContent = captureBtnLabel();

            if (canMark && nextPunchType !== null) {
                punchBtn.textContent = actionLabel();
                punchBtn.disabled = false;
                captureBtn.disabled = false;
                if (requirePunchPhoto && punchModalEl.classList.contains('show') && cameraStream) {
                    startLivePreview();
                }
            }

            prefetchLocation();
        }
    };

    const refreshStatus = async () => {
        const { data } = await api.get('/attendance/status');
        updatePanel(data.data);

        if (requirePunchPhoto && requireFaceMatch) {
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
