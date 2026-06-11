import axios from 'axios';
import api, { getErrorMessage } from './api';
import { initCompanyDescriptionEditor } from './company-editor';
import { openLogoLightbox } from './logo-lightbox';
import {
    COMPANY_ASYNC_FIELDS,
    COMPANY_FORM_FIELDS,
    getCompanyFieldValue,
    validateCompanyField,
    validateCompanyForm,
} from './company-validation';
import { setFlashMessage } from './form-utils';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('companyForm');

    if (!form) {
        return;
    }

    const companyId = form.dataset.companyId || null;
    const isEdit = Boolean(companyId);
    const logoInput = document.getElementById('logo');
    const logoPreviewWrap = document.getElementById('logoPreviewWrap');
    const logoPreviewImg = document.getElementById('logoPreviewImg');
    const logoViewBtn = document.getElementById('logoViewBtn');
    const submitButton = document.getElementById('companySubmitBtn') || form.querySelector('button[type="submit"]');
    const alertBox = document.getElementById('companyFormAlert');
    let isSubmitting = false;
    const descriptionEditor = initCompanyDescriptionEditor();

    const hideAlert = () => {
        if (!alertBox) {
            return;
        }

        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    };

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const getFeedbackEl = (field) => {
        let feedback = document.querySelector(`.ajax-feedback[data-for="${field}"]`);

        if (feedback) {
            return feedback;
        }

        const input = document.getElementById(field);

        if (!input) {
            return null;
        }

        feedback = document.createElement('div');
        feedback.className = 'ajax-feedback small mt-1';
        feedback.dataset.for = field;

        const fieldContainer = input.closest('[class*="col-"]') || input.parentElement;
        fieldContainer.appendChild(feedback);

        return feedback;
    };

    const setFieldState = (field, valid, message) => {
        const input = document.getElementById(field);
        const feedback = getFeedbackEl(field);
        const editor = field === 'description' ? document.getElementById('descriptionEditor') : null;

        if (!input && !editor) {
            return;
        }

        input?.classList.remove('is-valid', 'is-invalid');
        editor?.classList.remove('is-valid', 'is-invalid');

        if (message) {
            input?.classList.add(valid ? 'is-valid' : 'is-invalid');
            editor?.classList.add(valid ? 'is-valid' : 'is-invalid');
        }

        if (feedback) {
            feedback.textContent = message || '';
            feedback.className = `ajax-feedback small mt-1 ${valid ? 'text-success' : 'text-danger'}`;
            feedback.dataset.for = field;
        }
    };

    const clearFieldState = (field) => {
        const input = document.getElementById(field);
        const editor = field === 'description' ? document.getElementById('descriptionEditor') : null;
        const feedback = document.querySelector(`.ajax-feedback[data-for="${field}"]`);

        if (input) {
            input.classList.remove('is-valid', 'is-invalid');
        }

        if (editor) {
            editor.classList.remove('is-valid', 'is-invalid');
        }

        if (feedback) {
            feedback.textContent = '';
        }
    };

    const validateSyncField = (field) => {
        const value = field === 'logo'
            ? logoInput?.files?.[0] ?? null
            : getCompanyFieldValue(form, field);
        const message = validateCompanyField(field, value);

        if (message) {
            setFieldState(field, false, message);
            return false;
        }

        clearFieldState(field);
        return true;
    };

    const validateFieldAsync = async (field) => {
        const input = document.getElementById(field);

        if (!input) {
            return true;
        }

        const value = input.value.trim();

        if (value === '') {
            clearFieldState(field);
            return true;
        }

        if (!validateSyncField(field)) {
            return false;
        }

        try {
            const { data } = await api.post('/companies/check-field', {
                field,
                value,
                company_id: companyId,
            });

            const result = data.data;
            setFieldState(field, result.valid, result.message);

            return result.valid;
        } catch (error) {
            setFieldState(field, false, getErrorMessage(error, 'Unable to validate field.'));
            return false;
        }
    };

    const applyValidationErrors = (errors) => {
        Object.entries(errors).forEach(([field, message]) => {
            setFieldState(field, false, message);
        });
    };

    const focusFirstInvalidField = () => {
        const firstInvalid = form.querySelector('.is-invalid');

        if (!firstInvalid) {
            return;
        }

        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalid.focus({ preventScroll: true });
    };

    const setSubmitting = (submitting) => {
        if (!submitButton) {
            return;
        }

        isSubmitting = submitting;

        if (submitting) {
            if (!submitButton.dataset.originalHtml) {
                submitButton.dataset.originalHtml = submitButton.innerHTML;
            }

            submitButton.disabled = true;
            submitButton.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                ${isEdit ? 'Updating...' : 'Submitting...'}
            `;
            return;
        }

        submitButton.disabled = false;

        if (submitButton.dataset.originalHtml) {
            submitButton.innerHTML = submitButton.dataset.originalHtml;
        }
    };

    const syncDescriptionEditor = () => {
        descriptionEditor?.sync();
    };

    const runClientValidation = () => {
        hideAlert();
        syncDescriptionEditor();

        COMPANY_FORM_FIELDS.forEach(clearFieldState);
        clearFieldState('logo');

        const { valid, errors } = validateCompanyForm(form, logoInput?.files?.[0] ?? null);

        if (!valid) {
            applyValidationErrors(errors);
            showAlert('Please fix the highlighted errors before submitting.');
            focusFirstInvalidField();
            return false;
        }

        return true;
    };

    if (descriptionEditor) {
        descriptionEditor.quill.on('text-change', () => {
            if (isSubmitting) {
                return;
            }

            syncDescriptionEditor();
            validateSyncField('description');
        });
    }

    [...COMPANY_FORM_FIELDS, 'logo'].forEach((field) => {
        if (field === 'description') {
            return;
        }

        const input = document.getElementById(field);

        if (!input) {
            return;
        }

        const eventName = input.tagName === 'SELECT' || input.type === 'file' ? 'change' : 'blur';

        input.addEventListener(eventName, () => {
            if (isSubmitting) {
                return;
            }

            validateSyncField(field);

            if (COMPANY_ASYNC_FIELDS.includes(field)) {
                validateFieldAsync(field);
            }
        });
    });

    ['gstin', 'pan_number'].forEach((field) => {
        const input = document.getElementById(field);

        if (input) {
            input.addEventListener('input', () => {
                input.value = input.value.toUpperCase();
            });
        }
    });

    const foundedYear = document.getElementById('founded_year');

    if (foundedYear) {
        foundedYear.addEventListener('input', () => {
            foundedYear.value = foundedYear.value.replace(/\D/g, '').slice(0, 4);
        });
    }

    ['phone', 'contact_person_phone'].forEach((field) => {
        const input = document.getElementById(field);

        if (input) {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 10);
            });
        }
    });

    const postalCode = document.getElementById('postal_code');

    if (postalCode) {
        postalCode.addEventListener('input', () => {
            postalCode.value = postalCode.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    if (logoInput) {
        logoInput.addEventListener('change', (event) => {
            const file = event.target.files[0];

            validateSyncField('logo');

            if (!file) {
                return;
            }

            const reader = new FileReader();

            reader.onload = (e) => {
                if (logoPreviewImg) {
                    logoPreviewImg.src = e.target.result;
                }

                if (logoPreviewWrap) {
                    logoPreviewWrap.style.display = 'flex';
                }
            };

            reader.readAsDataURL(file);
        });
    }

    if (logoViewBtn) {
        logoViewBtn.addEventListener('click', (event) => {
            event.preventDefault();
            openLogoLightbox(logoPreviewImg?.src);
        });
    }

    const handleSubmit = async () => {
        if (isSubmitting) {
            return;
        }

        if (!runClientValidation()) {
            return;
        }

        const asyncFieldsToCheck = COMPANY_ASYNC_FIELDS.filter((field) => {
            const value = getCompanyFieldValue(form, field);
            return value.trim() !== '';
        });

        if (asyncFieldsToCheck.length > 0) {
            const asyncResults = await Promise.all(
                asyncFieldsToCheck.map((field) => validateFieldAsync(field)),
            );

            if (asyncResults.includes(false)) {
                showAlert('Please fix the highlighted errors before submitting.');
                focusFirstInvalidField();
                return;
            }
        }

        setSubmitting(true);
        syncDescriptionEditor();

        const formData = new FormData(form);

        try {
            const response = isEdit
                ? await api.post(`/companies/${companyId}`, formData, {
                    headers: { 'X-HTTP-Method-Override': 'PUT' },
                })
                : await api.post('/companies', formData);

            const redirectUrl = window.HRMS_WEB_ROUTES?.companiesIndex || '/companies';
            const message = response.data.message || 'Company saved successfully.';
            setFlashMessage(message);
            window.location.href = redirectUrl;
        } catch (error) {
            setSubmitting(false);
            showAlert(getErrorMessage(error));

            const backendErrors = error.response?.data?.errors;

            if (backendErrors) {
                Object.entries(backendErrors).forEach(([field, messages]) => {
                    const message = Array.isArray(messages) ? messages[0] : messages;
                    setFieldState(field, false, message);
                });
                focusFirstInvalidField();
            }
        }
    };

    submitButton?.addEventListener('click', handleSubmit);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        handleSubmit();
    });
});
