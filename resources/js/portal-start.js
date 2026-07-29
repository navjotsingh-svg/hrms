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
    const faceRequirePhotoInput = document.getElementById('attendance_require_punch_photo');
    const faceSaveBtn = document.getElementById('attendanceFaceSaveBtn');
    const faceDefaultThresholdEl = document.getElementById('attendanceFaceDefaultThreshold');
    const regularizationEnabledInput = document.getElementById('attendance_regularization_enabled');
    const regularizationCutoffInput = document.getElementById('attendance_regularization_previous_month_cutoff_day');
    const regularizationMaxRequestsInput = document.getElementById('attendance_regularization_max_requests_per_month');
    const regularizationBlockTodayInput = document.getElementById('attendance_regularization_block_current_day_until_complete');
    const regularizationSaveBtn = document.getElementById('attendanceRegularizationSaveBtn');

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

    const parseBooleanSetting = (value) => {
        if (value === true || value === false) {
            return value;
        }

        if (value === 1 || value === 0) {
            return Boolean(value);
        }

        if (value === '1' || value === 'true') {
            return true;
        }

        if (value === '0' || value === 'false') {
            return false;
        }

        return null;
    };

    const resolveToggleSetting = (explicitValue, resolvedValue, defaultValue) => {
        const explicit = parseBooleanSetting(explicitValue);

        if (explicit !== null) {
            return explicit;
        }

        const resolved = parseBooleanSetting(resolvedValue);

        if (resolved !== null) {
            return resolved;
        }

        return defaultValue !== false;
    };

    const syncFaceSettingAvailability = () => {
        const photoRequired = faceRequirePhotoInput?.checked ?? true;
        faceThresholdInput?.toggleAttribute('disabled', !photoRequired);
        faceRequireMatchInput?.toggleAttribute('disabled', !photoRequired);

        if (!photoRequired && faceRequireMatchInput) {
            faceRequireMatchInput.checked = false;
        }
    };

    const applyFaceSettings = (payload = {}) => {
        if (faceDefaultThresholdEl) {
            faceDefaultThresholdEl.textContent = String(payload.default_face_match_threshold ?? 90);
        }

        if (!faceThresholdInput) {
            return;
        }

        faceThresholdInput.value = payload.company_face_match_threshold ?? '';
        faceThresholdInput.placeholder = `Default: ${payload.default_face_match_threshold ?? 90}%`;

        if (faceRequirePhotoInput) {
            faceRequirePhotoInput.checked = resolveToggleSetting(
                payload.company_require_punch_photo,
                payload.require_punch_photo,
                payload.default_require_punch_photo,
            );
        }

        if (faceRequireMatchInput) {
            faceRequireMatchInput.checked = resolveToggleSetting(
                payload.company_require_face_match,
                payload.require_face_match,
                payload.default_require_face_match,
            );
        }

        syncFaceSettingAvailability();
    };

    const applyRegularizationSettings = (payload = {}) => {
        const settings = payload.regularization || {};

        if (regularizationEnabledInput) {
            regularizationEnabledInput.checked = resolveToggleSetting(
                settings.company_enabled,
                settings.enabled,
                settings.default_enabled,
            );
        }

        if (regularizationCutoffInput) {
            regularizationCutoffInput.value = settings.company_previous_month_cutoff_day
                ?? settings.previous_month_cutoff_day
                ?? settings.default_previous_month_cutoff_day
                ?? 2;
            regularizationCutoffInput.placeholder = `Default: ${settings.default_previous_month_cutoff_day ?? 2}`;
        }

        if (regularizationMaxRequestsInput) {
            regularizationMaxRequestsInput.value = settings.company_max_requests_per_month
                ?? settings.max_requests_per_month
                ?? settings.default_max_requests_per_month
                ?? '';
            regularizationMaxRequestsInput.placeholder = settings.default_max_requests_per_month
                ? `Default: ${settings.default_max_requests_per_month}`
                : 'No default limit';
        }

        if (regularizationBlockTodayInput) {
            regularizationBlockTodayInput.checked = resolveToggleSetting(
                settings.company_block_current_day_until_complete,
                settings.block_current_day_until_complete,
                settings.default_block_current_day_until_complete,
            );
        }
    };

    faceRequirePhotoInput?.addEventListener('change', syncFaceSettingAvailability);

    try {
        const [portalResponse, networkResponse] = await Promise.all([
            api.get('/portal-start'),
            api.get('/attendance/network-settings'),
        ]);
        applyPayload(portalResponse.data.data);
        applyNetworkSettings(networkResponse.data.data);
        applyFaceSettings(networkResponse.data.data);
        applyRegularizationSettings(networkResponse.data.data);
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

    regularizationSaveBtn?.addEventListener('click', async () => {
        if (isSubmitting) {
            return;
        }

        const cutoffRaw = regularizationCutoffInput?.value?.trim() ?? '';
        let previousMonthCutoffDay = null;

        if (cutoffRaw !== '') {
            const parsedCutoff = Number(cutoffRaw);

            if (!Number.isInteger(parsedCutoff) || parsedCutoff < 1 || parsedCutoff > 28) {
                showAlert('Previous month cutoff day must be a whole number between 1 and 28.', 'danger');
                return;
            }

            previousMonthCutoffDay = parsedCutoff;
        }

        const maxRaw = regularizationMaxRequestsInput?.value?.trim() ?? '';
        let maxRequestsPerMonth = null;

        if (maxRaw !== '') {
            const parsedMax = Number(maxRaw);

            if (!Number.isInteger(parsedMax) || parsedMax < 1 || parsedMax > 100) {
                showAlert('Max requests per month must be a whole number between 1 and 100.', 'danger');
                return;
            }

            maxRequestsPerMonth = parsedMax;
        }

        isSubmitting = true;
        setSubmitLoading(regularizationSaveBtn, true, { submittingText: 'Saving...' });

        try {
            const { data } = await api.put('/attendance/regularization-settings', {
                enabled: Boolean(regularizationEnabledInput?.checked),
                previous_month_cutoff_day: previousMonthCutoffDay,
                max_requests_per_month: maxRequestsPerMonth,
                block_current_day_until_complete: Boolean(regularizationBlockTodayInput?.checked),
            });
            applyRegularizationSettings(data.data || {});
            showAlert(data.message || 'Regularization policy updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(regularizationSaveBtn, false);
            regularizationSaveBtn.textContent = 'Save Regularization Policy';
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
                require_face_match: Boolean(faceRequireMatchInput?.checked),
                require_punch_photo: Boolean(faceRequirePhotoInput?.checked),
            });
            applyFaceSettings(data.data || {});
            showAlert(data.message || 'Attendance punch settings updated successfully.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            isSubmitting = false;
            setSubmitLoading(faceSaveBtn, false);
            faceSaveBtn.textContent = 'Save Punch Settings';
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
