import api, { getErrorMessage } from './api';
import { setSubmitLoading } from './form-utils';

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('portalStartForm');
    const alertBox = document.getElementById('portalStartAlert');
    const dateInput = document.getElementById('attendance_portal_start_date');
    const statusText = document.getElementById('portalStartStatus');
    const submitBtn = document.getElementById('portalStartSubmitBtn');

    if (!form || !dateInput) {
        return;
    }

    let isSubmitting = false;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alertBox.classList.remove('d-none');
    };

    const lockForm = () => {
        dateInput.readOnly = true;
        dateInput.classList.add('bg-light');
        submitBtn?.classList.add('d-none');
    };

    const updateStatusText = (payload) => {
        if (!statusText) {
            return;
        }

        if (!payload.is_configured) {
            statusText.textContent = 'Not configured yet. Set the portal start date once — it cannot be changed afterward.';
            return;
        }

        statusText.textContent = `Attendance is tracked from ${payload.attendance_portal_start_date_label} onward. This date is locked and cannot be modified. After that, each employee is tracked from the latest of this date and their joining date. Unmarked working days show as absent.`;
    };

    const applyPayload = (payload) => {
        dateInput.value = payload.attendance_portal_start_date || '';
        updateStatusText(payload);

        if (payload.is_locked || payload.is_configured) {
            lockForm();
        }
    };

    const validateYear = () => {
        const value = dateInput.value;

        if (!value) {
            dateInput.setCustomValidity('Portal start date is required.');
            return false;
        }

        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            dateInput.setCustomValidity('Date must use a 4-digit year (YYYY-MM-DD).');
            return false;
        }

        dateInput.setCustomValidity('');
        return true;
    };

    dateInput.addEventListener('input', validateYear);

    const allowedIpsInput = document.getElementById('attendance_allowed_ips');
    const networkSaveBtn = document.getElementById('attendanceNetworkSaveBtn');
    const faceThresholdInput = document.getElementById('attendance_face_match_threshold');
    const faceRequireMatchInput = document.getElementById('attendance_require_face_match');
    const faceSaveBtn = document.getElementById('attendanceFaceSaveBtn');
    const faceDefaultThresholdEl = document.getElementById('attendanceFaceDefaultThreshold');

    const parseAllowedIps = (value) => value
        .split(/[\r\n,]+/)
        .map((line) => line.trim())
        .filter(Boolean);

    const applyNetworkSettings = (payload = {}) => {
        if (!allowedIpsInput) {
            return;
        }

        allowedIpsInput.value = (payload.attendance_allowed_ips || []).join('\n');
    };

    const applyFaceSettings = (payload = {}) => {
        if (faceDefaultThresholdEl) {
            faceDefaultThresholdEl.textContent = String(payload.default_face_match_threshold ?? 80);
        }

        if (!faceThresholdInput) {
            return;
        }

        faceThresholdInput.value = payload.company_face_match_threshold ?? '';
        faceThresholdInput.placeholder = `Default: ${payload.default_face_match_threshold ?? 80}%`;

        if (faceRequireMatchInput) {
            faceRequireMatchInput.checked = payload.require_face_match !== false;
        }
    };

    try {
        const [portalResponse, networkResponse] = await Promise.all([
            api.get('/portal-start'),
            api.get('/attendance/network-settings'),
        ]);
        applyPayload(portalResponse.data.data);
        applyNetworkSettings(networkResponse.data.data);
        applyFaceSettings(networkResponse.data.data);
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    networkSaveBtn?.addEventListener('click', async () => {
        if (isSubmitting || !allowedIpsInput) {
            return;
        }

        isSubmitting = true;
        setSubmitLoading(networkSaveBtn, true, { submittingText: 'Saving...' });

        try {
            const { data } = await api.put('/attendance/network-settings', {
                attendance_allowed_ips: parseAllowedIps(allowedIpsInput.value),
            });
            applyNetworkSettings(data.data || {});
            showAlert(data.message || 'Attendance network settings updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(networkSaveBtn, false);
            networkSaveBtn.textContent = 'Save Network Settings';
        }
    });

    faceSaveBtn?.addEventListener('click', async () => {
        if (isSubmitting || !faceThresholdInput) {
            return;
        }

        const rawValue = faceThresholdInput.value.trim();
        let faceMatchThreshold = null;

        if (rawValue !== '') {
            const parsed = Number(rawValue);

            if (!Number.isInteger(parsed) || parsed < 1 || parsed > 100) {
                showAlert('Face match must be a whole number between 1 and 100.', 'danger');
                return;
            }

            faceMatchThreshold = parsed;
        }

        isSubmitting = true;
        setSubmitLoading(faceSaveBtn, true, { submittingText: 'Saving...' });

        try {
            const { data } = await api.put('/attendance/face-settings', {
                face_match_threshold: faceMatchThreshold,
                require_face_match: faceRequireMatchInput?.checked ?? true,
            });
            applyFaceSettings(data.data || {});
            showAlert(data.message || 'Face verification settings updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(faceSaveBtn, false);
            faceSaveBtn.textContent = 'Save Face Settings';
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSubmitting || dateInput.readOnly) {
            return;
        }

        if (!validateYear()) {
            dateInput.reportValidity();
            return;
        }

        if (!window.confirm('Save portal start day? This date cannot be changed once saved.')) {
            return;
        }

        isSubmitting = true;

        try {
            setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });
            const { data } = await api.put('/portal-start', {
                attendance_portal_start_date: dateInput.value,
            });

            applyPayload(data.data);
            showAlert(data.message || 'Portal start day saved successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            setSubmitLoading(submitBtn, false);
            if (submitBtn) {
                submitBtn.textContent = 'Save Portal Start Day';
            }
            isSubmitting = false;
        }
    });
});
