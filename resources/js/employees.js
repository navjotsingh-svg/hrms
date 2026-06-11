import api, { getErrorMessage } from './api';
import jQuery from './jquery-select2';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';
import {
    addMonths,
    addYears,
    applyBackendErrors,
    clearFormErrors,
    debounce,
    flashMessageType,
    focusFirstInvalidField,
    hasValidDateYear,
    isValidEmail,
    isValidMobile,
    normalizeMobile,
    setFlashMessage,
    setFieldError,
    setSubmitLoading,
    toDateInputValue,
} from './form-utils';

const webRoutes = () => window.HRMS_WEB_ROUTES || {};
const EXCLUDED_ROLE_SLUGS = ['super_admin', 'company_admin'];
const ASYNC_FIELDS = ['email', 'phone', 'employee_code'];
const TOTAL_STEPS = 4;

const EMPLOYMENT_LABELS = {
    full_time: 'Full Time',
    part_time: 'Part Time',
    contract: 'Contract',
    intern: 'Intern',
};

const PROBATION_STATUS_LABELS = {
    on_probation: 'On Probation',
    confirmed: 'Confirmed',
    extended: 'Extended',
    not_applicable: 'Not Applicable',
};

const formatCurrency = (value) => new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 0,
}).format(Number(value) || 0);

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('employeeForm');
    const alertBox = document.getElementById('employeeFormAlert');
    const submitBtn = document.getElementById('employeeSubmitBtn');
    const prevBtn = document.getElementById('wizardPrevBtn');
    const nextBtn = document.getElementById('wizardNextBtn');
    const progressFill = document.getElementById('wizardProgressFill');
    const reviewSummary = document.getElementById('reviewSummary');
    const portalAccessSection = document.getElementById('portalAccessSection');
    const portalAccessStatus = document.getElementById('portalAccessStatus');
    const portalAccessBadge = document.getElementById('portalAccessBadge');
    const grantPortalAccessWrap = document.getElementById('grantPortalAccessWrap');
    const resendWelcomeEmailWrap = document.getElementById('resendWelcomeEmailWrap');
    const resendWelcomeEmailHint = document.getElementById('resendWelcomeEmailHint');
    const resendWelcomeEmailBtn = document.getElementById('resendWelcomeEmailBtn');
    const routes = webRoutes();
    const employeeId = form?.dataset.employeeId;
    const isUpdate = Boolean(employeeId);

    if (!form) {
        return;
    }

    if (isUpdate && submitBtn) {
        submitBtn.textContent = 'Update Employee';
    }

    let currentStep = 1;
    let hasPortalAccess = false;
    let isSubmitting = false;
    let departmentOptions = [];
    let shiftOptions = [];
    let roleOptions = [];
    let managerOptions = [];
    let departmentSelect = null;

    const today = new Date();
    const maxJoiningDate = toDateInputValue(today);
    const maxDobDate = toDateInputValue(addYears(today, -18));
    const minDobDate = toDateInputValue(addYears(today, -100));

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const getValue = (id) => form.querySelector(`#${id}`)?.value?.trim() ?? '';
    const getSelectText = (id) => {
        const select = form.querySelector(`#${id}`);
        return select?.options[select.selectedIndex]?.text?.trim() || '—';
    };

    const getBasicSalary = () => parseFloat(getValue('basic_salary')) || 0;

    const getMonthlyCtc = () => {
        const annualCtc = parseFloat(getValue('annual_ctc')) || 0;

        return annualCtc > 0 ? annualCtc / 12 : 0;
    };

    const getHraAmount = () => getMonthlyCtc() * (parseFloat(getValue('hra_percent')) || 0) / 100;

    const getSpecialAllowanceAmount = () => getMonthlyCtc() * (parseFloat(getValue('special_allowance_percent')) || 0) / 100;

    const getDepartmentIds = () => {
        const select = form.querySelector('#department_ids');
        if (!select) {
            return [];
        }

        return Array.from(select.selectedOptions).map((option) => Number(option.value)).filter(Boolean);
    };

    const getDepartmentLabels = () => {
        const select = form.querySelector('#department_ids');
        if (!select) {
            return '—';
        }

        const labels = Array.from(select.selectedOptions).map((option) => option.textContent.trim()).filter(Boolean);

        return labels.length ? labels.join(', ') : '—';
    };

    const getShiftLabel = () => getSelectText('shift_id');

    const updateShiftPreview = () => {
        const preview = document.getElementById('shiftTimingPreview');
        const timingText = document.getElementById('shiftTimingText');
        const breakText = document.getElementById('shiftBreakText');
        const shiftId = getValue('shift_id');
        const shift = shiftOptions.find((option) => String(option.value) === String(shiftId));

        if (!preview || !timingText) {
            return;
        }

        if (!shift) {
            preview.classList.add('d-none');
            return;
        }

        timingText.textContent = shift.timing_summary || shift.time_range || '—';
        if (breakText) {
            breakText.textContent = shift.is_overnight ? 'Overnight shift' : '';
        }
        preview.classList.remove('d-none');
    };

    const getPortalReviewText = () => {
        if (hasPortalAccess) {
            return 'Portal access active';
        }

        if (form.querySelector('#give_portal_access')?.checked || form.querySelector('#grant_portal_access')?.checked) {
            return 'Portal access granted';
        }

        return 'No portal access';
    };

    const formatAddress = () => {
        const parts = [
            getValue('address_line_1'),
            getValue('address_line_2'),
            getValue('city'),
            getValue('state'),
            getValue('postal_code'),
            getValue('country'),
        ].filter(Boolean);

        return parts.length ? parts.join(', ') : '—';
    };

    const isProbationApplicable = () => form.querySelector('#probation_applicable')?.checked ?? false;

    const toggleProbationFields = () => {
        const show = isProbationApplicable();
        form.querySelectorAll('.probation-field').forEach((element) => {
            element.classList.toggle('d-none', !show);
        });
        form.querySelector('.probation-fields-grid')?.classList.toggle('is-hidden', !show);
    };

    const calculateProbationEndDate = (force = false) => {
        if (!isProbationApplicable()) {
            return;
        }

        const joiningDate = getValue('joining_date');
        const months = parseInt(getValue('probation_period_months'), 10) || 0;
        const endInput = form.querySelector('#probation_end_date');

        if (!joiningDate || !months || !endInput) {
            return;
        }

        if (force || !endInput.dataset.manualEdit) {
            endInput.value = toDateInputValue(addMonths(new Date(`${joiningDate}T00:00:00`), months));
        }

        syncProbationStatusFromEndDate();
    };

    const syncProbationStatusFromEndDate = () => {
        if (!isProbationApplicable()) {
            return;
        }

        const endDate = getValue('probation_end_date');
        const statusSelect = form.querySelector('#probation_status');

        if (!endDate || !statusSelect) {
            return;
        }

        if (endDate < maxJoiningDate && statusSelect.value === 'on_probation' && !statusSelect.dataset.manualStatus) {
            statusSelect.value = 'confirmed';
        }
    };

    const syncSalaryEffectiveFrom = (force = false) => {
        const joiningDate = getValue('joining_date');
        const effectiveInput = form.querySelector('#salary_effective_from');

        if (!joiningDate || !effectiveInput) {
            return;
        }

        if (force || !effectiveInput.dataset.manualEdit) {
            effectiveInput.value = joiningDate;
        }
    };

    const setupDateConstraints = () => {
        const dobInput = form.querySelector('#date_of_birth');
        const joiningInput = form.querySelector('#joining_date');
        const salaryEffectiveInput = form.querySelector('#salary_effective_from');

        if (dobInput) {
            dobInput.max = maxDobDate;
            dobInput.min = minDobDate;
        }

        if (joiningInput) {
            joiningInput.max = maxJoiningDate;
        }

        if (salaryEffectiveInput) {
            salaryEffectiveInput.max = maxJoiningDate;
        }
    };

    const validateDateField = (field, value) => {
        if (!value) {
            return true;
        }

        if (!hasValidDateYear(value)) {
            setFieldError(form, field, 'Year must be exactly 4 digits.');
            return false;
        }

        if (field === 'date_of_birth') {
            if (value > maxDobDate) {
                setFieldError(form, field, 'Employee must be at least 18 years old.');
                return false;
            }

            if (value < minDobDate) {
                setFieldError(form, field, 'Please enter a valid date of birth.');
                return false;
            }
        }

        if (field === 'joining_date' && value > maxJoiningDate) {
            setFieldError(form, field, 'Joining date cannot be in the future.');
            return false;
        }

        if (field === 'salary_effective_from' && value > maxJoiningDate) {
            setFieldError(form, field, 'Effective date cannot be in the future.');
            return false;
        }

        if (field === 'probation_end_date') {
            const joiningDate = getValue('joining_date');

            if (joiningDate && value < joiningDate) {
                setFieldError(form, field, 'Probation end date cannot be before joining date.');
                return false;
            }
        }

        return true;
    };

    const calculateMonthlyGross = () => {
        const fixedFields = ['conveyance_allowance', 'medical_allowance', 'other_allowance'];
        const fixedTotal = fixedFields.reduce((sum, field) => sum + (parseFloat(getValue(field)) || 0), 0);

        return getBasicSalary() + getHraAmount() + getSpecialAllowanceAmount() + fixedTotal;
    };

    const updateSalarySummary = () => {
        const annual = parseFloat(getValue('annual_ctc')) || 0;
        const monthly = calculateMonthlyGross();
        document.getElementById('summaryAnnualCtc').textContent = formatCurrency(annual);
        document.getElementById('summaryMonthlyGross').textContent = formatCurrency(monthly);

        const hraPreview = document.getElementById('hraAmountPreview');
        const specialPreview = document.getElementById('specialAllowanceAmountPreview');

        if (hraPreview) {
            hraPreview.textContent = formatCurrency(getHraAmount());
        }

        if (specialPreview) {
            specialPreview.textContent = formatCurrency(getSpecialAllowanceAmount());
        }
    };

    const validateSyncField = (field) => {
        const input = form.querySelector(`#${field}`);
        if (!input) {
            return true;
        }

        const value = field === 'phone' ? normalizeMobile(input.value) : input.value.trim();

        const requiredMessages = {
            first_name: 'First name is required.',
            email: 'Email is required.',
            phone: 'Mobile number is required.',
            employee_code: 'Employee code is required.',
            gender: 'Gender is required.',
            date_of_birth: 'Date of birth is required.',
            role_id: 'Role is required.',
            shift_id: 'Work shift is required.',
            joining_date: 'Joining date is required.',
            employment_type: 'Employment type is required.',
            status: 'Status is required.',
            annual_ctc: 'Annual CTC is required.',
            basic_salary: 'Basic salary is required.',
            salary_effective_from: 'Salary effective date is required.',
            probation_period_months: 'Probation period is required.',
            probation_end_date: 'Probation end date is required.',
        };

        if (requiredMessages[field] && !value) {
            setFieldError(form, field, requiredMessages[field]);
            return false;
        }

        if ((field === 'email' || field === 'personal_email') && value && !isValidEmail(value)) {
            setFieldError(form, field, 'Please enter a valid email address.');
            return false;
        }

        if (field === 'phone' && value && !isValidMobile(value)) {
            setFieldError(form, field, 'Mobile number must be exactly 10 digits.');
            return false;
        }

        if (field === 'annual_ctc' && value && parseFloat(value) <= 0) {
            setFieldError(form, field, 'Annual CTC must be greater than zero.');
            return false;
        }

        if (field === 'basic_salary' && value && parseFloat(value) <= 0) {
            setFieldError(form, field, 'Basic salary must be greater than zero.');
            return false;
        }

        if (field === 'postal_code' && value && !/^[0-9]{4,10}$/.test(value)) {
            setFieldError(form, field, 'Pincode must be 4 to 10 digits.');
            return false;
        }

        if (['date_of_birth', 'joining_date', 'salary_effective_from', 'probation_end_date'].includes(field) && value) {
            if (!validateDateField(field, value)) {
                return false;
            }
        }

        if (['hra_percent', 'special_allowance_percent'].includes(field) && value) {
            const percent = parseFloat(value);
            if (percent < 0 || percent > 100) {
                setFieldError(form, field, 'Percentage must be between 0 and 100.');
                return false;
            }
        }

        if (['annual_ctc', 'basic_salary', 'conveyance_allowance', 'medical_allowance', 'other_allowance'].includes(field) && value) {
            if (parseFloat(value) < 0) {
                setFieldError(form, field, 'Amount cannot be negative.');
                return false;
            }
        }

        setFieldError(form, field, '');
        return true;
    };

    const validateFieldAsync = async (field) => {
        const input = form.querySelector(`#${field}`);
        if (!input) {
            return true;
        }

        const value = field === 'phone' ? normalizeMobile(input.value) : input.value.trim();
        if (!value || !validateSyncField(field)) {
            return !value ? validateSyncField(field) : false;
        }

        if (!ASYNC_FIELDS.includes(field)) {
            return true;
        }

        try {
            const { data } = await api.post('/employees/check-field', {
                field,
                value,
                employee_id: employeeId || null,
            });

            const result = data.data;
            setFieldError(form, field, result.valid ? '' : result.message);
            if (result.valid) {
                input.classList.add('is-valid');
            }
            return result.valid;
        } catch (error) {
            setFieldError(form, field, getErrorMessage(error, 'Unable to validate field.'));
            return false;
        }
    };

    const stepFields = (step) => {
        if (step === 1) {
            return ['first_name', 'email', 'phone', 'employee_code', 'gender', 'date_of_birth'];
        }
        if (step === 2) {
            const fields = ['role_id', 'shift_id', 'joining_date', 'employment_type', 'status'];

            if (isProbationApplicable()) {
                fields.push('probation_period_months', 'probation_end_date');
            }

            return fields;
        }
        if (step === 3) {
            return ['annual_ctc', 'basic_salary', 'salary_effective_from', 'hra_percent', 'special_allowance_percent', 'conveyance_allowance', 'medical_allowance', 'other_allowance'];
        }
        return [];
    };

    const validateStep = async (step) => {
        const fields = stepFields(step);
        let valid = true;

        for (const field of fields) {
            if (!validateSyncField(field)) {
                valid = false;
            }
        }

        if (!valid) {
            return false;
        }

        if (step === 1) {
            for (const field of ASYNC_FIELDS) {
                const fieldValid = await validateFieldAsync(field);
                if (!fieldValid) {
                    valid = false;
                }
            }
        }

        return valid;
    };

    const updateStepUi = () => {
        form.querySelectorAll('[data-step-panel]').forEach((panel) => {
            panel.classList.toggle('active', Number(panel.dataset.stepPanel) === currentStep);
        });

        form.querySelectorAll('.wizard-step').forEach((stepEl) => {
            const step = Number(stepEl.dataset.step);
            stepEl.classList.remove('active', 'completed');
            if (step === currentStep) {
                stepEl.classList.add('active');
            } else if (step < currentStep) {
                stepEl.classList.add('completed');
                stepEl.querySelector('.wizard-step-circle').innerHTML = '✓';
            } else {
                stepEl.querySelector('.wizard-step-circle').innerHTML = `<span>${step}</span>`;
            }
        });

        if (progressFill) {
            progressFill.style.width = `${((currentStep - 1) / (TOTAL_STEPS - 1)) * 100}%`;
        }

        prevBtn?.classList.toggle('d-none', currentStep === 1);
        nextBtn?.classList.toggle('d-none', currentStep === TOTAL_STEPS);
        submitBtn?.classList.toggle('d-none', currentStep !== TOTAL_STEPS);

        if (currentStep === TOTAL_STEPS) {
            buildReviewSummary();
        }

        if (currentStep === 3) {
            syncSalaryEffectiveFrom();
        }

        if (currentStep === 2) {
            ensureDepartmentSelect2();
        }
    };

    const buildReviewSummary = () => {
        if (!reviewSummary) {
            return;
        }

        const portalText = getPortalReviewText();

        const sections = [
            {
                title: 'Personal',
                rows: [
                    ['Name', `${getValue('first_name')} ${getValue('last_name')}`.trim()],
                    ['Work Email', getValue('email')],
                    ['Personal Email', getValue('personal_email') || '—'],
                    ['Mobile', getValue('phone')],
                    ['Employee Code', getValue('employee_code')],
                    ['Gender', getSelectText('gender')],
                    ['Address', formatAddress()],
                ],
            },
            {
                title: 'Employment',
                rows: [
                    ['Department', getDepartmentLabels()],
                    ['Shift', getShiftLabel()],
                    ['Role', getSelectText('role_id')],
                    ['Designation', getValue('designation') || '—'],
                    ['Manager', getSelectText('manager_id')],
                    ['Joining', getValue('joining_date') || '—'],
                    ['Type', EMPLOYMENT_LABELS[getValue('employment_type')] || '—'],
                    ['Status', getValue('status') === 'active' ? 'Active' : 'Inactive'],
                    ['Probation', isProbationApplicable()
                        ? `${getSelectText('probation_period_months')} — ${PROBATION_STATUS_LABELS[getValue('probation_status')] || '—'}`
                        : PROBATION_STATUS_LABELS.not_applicable],
                    ...(isProbationApplicable()
                        ? [['Probation Ends', getValue('probation_end_date') || '—']]
                        : []),
                ],
            },
            {
                title: 'Salary',
                rows: [
                    ['Annual CTC', formatCurrency(getValue('annual_ctc'))],
                    ['Monthly Gross', formatCurrency(calculateMonthlyGross())],
                    ['Basic', formatCurrency(getValue('basic_salary'))],
                    ['HRA', `${getValue('hra_percent') || 0}% (${formatCurrency(getHraAmount())})`],
                    ['Special Allowance', `${getValue('special_allowance_percent') || 0}% (${formatCurrency(getSpecialAllowanceAmount())})`],
                    ['Effective From', getValue('salary_effective_from') || '—'],
                    ['PF', form.querySelector('#pf_applicable')?.checked ? 'Yes' : 'No'],
                    ['ESI', form.querySelector('#esi_applicable')?.checked ? 'Yes' : 'No'],
                    ['Prof. Tax', form.querySelector('#professional_tax_applicable')?.checked ? 'Yes' : 'No'],
                ],
            },
            {
                title: 'Access',
                rows: [['Portal', portalText]],
            },
        ];

        reviewSummary.innerHTML = sections.map((section) => `
            <div class="review-card">
                <div class="review-card-title">${section.title}</div>
                ${section.rows.map(([label, value]) => `
                    <div class="review-row">
                        <span class="review-row-label">${label}</span>
                        <span class="review-row-value">${value || '—'}</span>
                    </div>
                `).join('')}
            </div>
        `).join('');
    };

    const setInputValue = (id, value) => {
        const input = form.querySelector(`#${id}`);
        if (input) {
            input.value = value ?? '';
        }
    };

    const setDateInput = (id, value) => {
        const input = form.querySelector(`#${id}`);
        if (input) {
            input.value = value ? toDateInputValue(value) : '';
        }
    };

    const setSelectValue = (id, value) => {
        const select = form.querySelector(`#${id}`);
        if (select) {
            select.value = value ?? '';
        }
    };

    const initDepartmentSelect2 = (selectedIds = []) => {
        const select = form.querySelector('#department_ids');
        if (!select || typeof jQuery.fn.select2 !== 'function') {
            return;
        }

        if (departmentSelect?.hasClass?.('select2-hidden-accessible')) {
            departmentSelect.select2('destroy');
        }
        departmentSelect = null;

        departmentSelect = jQuery(select).select2({
            theme: 'bootstrap-5',
            placeholder: select.dataset.placeholder || 'Select departments',
            allowClear: true,
            width: '100%',
            closeOnSelect: false,
            dropdownParent: jQuery(select).closest('.employee-wizard'),
        });

        if (selectedIds.length) {
            departmentSelect.val(selectedIds.map(String)).trigger('change');
        }

        departmentSelect.off('change.department').on('change.department', () => {
            if (currentStep === TOTAL_STEPS) {
                buildReviewSummary();
            }
        });
    };

    const ensureDepartmentSelect2 = () => {
        if (departmentOptions.length === 0) {
            return;
        }

        const selectedIds = getDepartmentIds();

        if (!departmentSelect || !form.querySelector('#department_ids')?.classList.contains('select2-hidden-accessible')) {
            initDepartmentSelect2(selectedIds);
        }
    };

    const setSelectOptions = (selectId, options, placeholder) => {
        const select = form.querySelector(`#${selectId}`);
        if (!select) {
            return;
        }
        const currentValue = select.value;
        select.innerHTML = `<option value="">${placeholder}</option>` + options
            .map((option) => `<option value="${option.value}">${option.label}</option>`)
            .join('');
        if (currentValue) {
            select.value = currentValue;
        }
    };

    const loadDepartments = async (selectedIds = []) => {
        const { data } = await api.get('/departments', { params: { per_page: 100, status: 'active' } });
        departmentOptions = (data.data.departments || []).map((d) => ({ value: d.id, label: d.name }));

        const select = form.querySelector('#department_ids');
        if (!select) {
            return;
        }

        select.innerHTML = '<option></option>' + departmentOptions
            .map((option) => `<option value="${option.value}">${option.label}</option>`)
            .join('');

        initDepartmentSelect2(selectedIds);
    };

    const loadShifts = async (selectedId = null) => {
        const { data } = await api.get('/shifts', { params: { per_page: 100, status: 'active' } });
        shiftOptions = (data.data.shifts || []).map((shift) => ({
            value: shift.id,
            label: shift.name,
            time_range: shift.time_range,
            timing_summary: shift.timing_summary,
            is_overnight: shift.is_overnight,
        }));

        const select = form.querySelector('#shift_id');
        if (!select) {
            return;
        }

        select.innerHTML = '<option value="">Select shift</option>' + shiftOptions
            .map((option) => `<option value="${option.value}">${option.label} (${option.time_range})</option>`)
            .join('');

        if (selectedId) {
            select.value = String(selectedId);
        }

        updateShiftPreview();
    };

    const loadRoles = async () => {
        const { data } = await api.get('/roles');
        roleOptions = (data.data.roles || [])
            .filter((role) => !EXCLUDED_ROLE_SLUGS.includes(role.slug))
            .map((role) => ({ value: role.id, label: role.name }));
        setSelectOptions('role_id', roleOptions, 'Select role');
    };

    const loadManagers = async (excludeId = null) => {
        const { data } = await api.get('/employees', { params: { per_page: 100, status: 'active' } });
        managerOptions = (data.data.employees || [])
            .filter((employee) => String(employee.id) !== String(excludeId))
            .map((employee) => ({ value: employee.id, label: `${employee.full_name} (${employee.employee_code})` }));
        setSelectOptions('manager_id', managerOptions, 'No manager');
    };

    const updatePortalAccessUi = () => {
        if (!employeeId) {
            portalAccessSection?.classList.remove('d-none');
            portalAccessStatus?.classList.add('d-none');
            return;
        }
        portalAccessSection?.classList.add('d-none');
        portalAccessStatus?.classList.remove('d-none');
        if (hasPortalAccess) {
            portalAccessBadge.textContent = 'Portal access active';
            portalAccessBadge.className = 'badge text-bg-success';
            grantPortalAccessWrap?.classList.add('d-none');
            resendWelcomeEmailWrap?.classList.remove('d-none');
            resendWelcomeEmailHint?.classList.remove('d-none');
        } else {
            portalAccessBadge.textContent = 'No portal access';
            portalAccessBadge.className = 'badge text-bg-secondary';
            grantPortalAccessWrap?.classList.remove('d-none');
            resendWelcomeEmailWrap?.classList.add('d-none');
            resendWelcomeEmailHint?.classList.add('d-none');
        }
    };

    resendWelcomeEmailBtn?.addEventListener('click', async () => {
        if (!employeeId || !hasPortalAccess || resendWelcomeEmailBtn.disabled) {
            return;
        }

        if (!window.confirm('Send a new welcome email with a freshly generated password? The employee will need to use the new password to sign in.')) {
            return;
        }

        resendWelcomeEmailBtn.disabled = true;
        const previousLabel = resendWelcomeEmailBtn.textContent;
        resendWelcomeEmailBtn.textContent = 'Sending...';

        try {
            const { data } = await api.post(`/employees/${employeeId}/resend-welcome-email`);
            showAlert(data.message || 'Welcome email sent with a new login password.', 'success');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            resendWelcomeEmailBtn.disabled = false;
            resendWelcomeEmailBtn.textContent = previousLabel;
        }
    });

    const getPayload = () => {
        const nullable = (value) => (value === '' || value === null ? null : value);
        const optionalInt = (value) => (value ? Number(value) : null);
        const num = (id) => parseFloat(getValue(id)) || 0;

        const payload = {
            first_name: getValue('first_name'),
            last_name: nullable(getValue('last_name')),
            email: getValue('email'),
            personal_email: nullable(getValue('personal_email')),
            phone: normalizeMobile(getValue('phone')),
            employee_code: getValue('employee_code'),
            department_ids: getDepartmentIds(),
            role_id: Number(getValue('role_id')),
            shift_id: Number(getValue('shift_id')),
            manager_id: optionalInt(getValue('manager_id')),
            designation: nullable(getValue('designation')),
            joining_date: getValue('joining_date'),
            gender: getValue('gender'),
            date_of_birth: getValue('date_of_birth'),
            employment_type: getValue('employment_type') || 'full_time',
            status: getValue('status') || 'active',
            probation_applicable: isProbationApplicable(),
            probation_period_months: isProbationApplicable() ? Number(getValue('probation_period_months')) : null,
            probation_end_date: isProbationApplicable() ? getValue('probation_end_date') : null,
            probation_status: isProbationApplicable() ? getValue('probation_status') : 'not_applicable',
            address_line_1: nullable(getValue('address_line_1')),
            address_line_2: nullable(getValue('address_line_2')),
            city: nullable(getValue('city')),
            state: nullable(getValue('state')),
            country: nullable(getValue('country')),
            postal_code: nullable(getValue('postal_code')),
            annual_ctc: num('annual_ctc'),
            basic_salary: num('basic_salary'),
            hra_percent: num('hra_percent'),
            special_allowance_percent: num('special_allowance_percent'),
            conveyance_allowance: num('conveyance_allowance'),
            medical_allowance: num('medical_allowance'),
            other_allowance: num('other_allowance'),
            pf_applicable: form.querySelector('#pf_applicable')?.checked ?? false,
            esi_applicable: form.querySelector('#esi_applicable')?.checked ?? false,
            professional_tax_applicable: form.querySelector('#professional_tax_applicable')?.checked ?? true,
            salary_effective_from: getValue('salary_effective_from'),
        };

        if (!employeeId) {
            payload.give_portal_access = form.querySelector('#give_portal_access')?.checked ?? false;
        } else if (!hasPortalAccess && form.querySelector('#grant_portal_access')?.checked) {
            payload.give_portal_access = true;
        }

        return payload;
    };

    const populateForm = async (employee) => {
        setInputValue('first_name', employee.first_name);
        setInputValue('last_name', employee.last_name);
        setInputValue('email', employee.email);
        setInputValue('personal_email', employee.personal_email);
        setInputValue('phone', employee.phone);
        setInputValue('employee_code', employee.employee_code);
        setSelectValue('gender', employee.gender);
        setDateInput('date_of_birth', employee.date_of_birth);
        setInputValue('address_line_1', employee.address_line_1);
        setInputValue('address_line_2', employee.address_line_2);
        setInputValue('city', employee.city);
        setInputValue('state', employee.state);
        setInputValue('country', employee.country || 'India');
        setInputValue('postal_code', employee.postal_code);

        const departmentIds = employee.department_ids?.length
            ? employee.department_ids
            : (employee.department_id ? [employee.department_id] : []);

        await loadDepartments(departmentIds.map(Number));
        await loadShifts(employee.shift_id || null);

        setSelectValue('role_id', employee.role_id);
        setSelectValue('manager_id', employee.manager_id);
        setInputValue('designation', employee.designation);
        setDateInput('joining_date', employee.joining_date);
        setSelectValue('employment_type', employee.employment_type || 'full_time');
        setSelectValue('status', employee.status || 'active');

        const probationCheckbox = form.querySelector('#probation_applicable');
        if (probationCheckbox) {
            probationCheckbox.checked = employee.probation_applicable ?? true;
        }
        setInputValue('probation_period_months', employee.probation_period_months || 3);
        setDateInput('probation_end_date', employee.probation_end_date);
        setSelectValue('probation_status', employee.probation_status || 'on_probation');
        syncProbationStatusFromEndDate();

        if (employee.salary) {
            const salary = employee.salary;
            setInputValue('annual_ctc', salary.annual_ctc ?? '');
            setInputValue('basic_salary', salary.basic_salary ?? '');
            setInputValue('hra_percent', salary.hra_percent ?? 40);
            setInputValue('special_allowance_percent', salary.special_allowance_percent ?? 0);
            setInputValue('conveyance_allowance', salary.conveyance_allowance ?? 0);
            setInputValue('medical_allowance', salary.medical_allowance ?? 0);
            setInputValue('other_allowance', salary.other_allowance ?? 0);
            setDateInput('salary_effective_from', salary.salary_effective_from);
            form.querySelector('#pf_applicable').checked = Boolean(salary.pf_applicable);
            form.querySelector('#esi_applicable').checked = Boolean(salary.esi_applicable);
            form.querySelector('#professional_tax_applicable').checked = Boolean(salary.professional_tax_applicable);
        }

        hasPortalAccess = Boolean(employee.has_portal_access);
        updateShiftPreview();
        toggleProbationFields();
        updateSalarySummary();
        updatePortalAccessUi();
    };

    ASYNC_FIELDS.forEach((field) => {
        const input = form.querySelector(`#${field}`);
        if (!input) {
            return;
        }

        input.addEventListener('blur', () => {
            if (!isSubmitting) {
                validateSyncField(field);
                validateFieldAsync(field);
            }
        });

        input.addEventListener('input', debounce(() => {
            if (isSubmitting) {
                return;
            }
            const value = field === 'phone' ? normalizeMobile(input.value) : input.value.trim();
            if (!value) {
                setFieldError(form, field, '');
                input.classList.remove('is-valid');
                return;
            }
            validateSyncField(field);
            if ((field === 'email' && isValidEmail(value)) || (field === 'phone' && isValidMobile(value)) || field === 'employee_code') {
                validateFieldAsync(field);
            }
        }));
    });

    form.querySelector('#phone')?.addEventListener('input', (event) => {
        event.target.value = event.target.value.replace(/\D/g, '').slice(0, 10);
    });

    form.querySelector('#postal_code')?.addEventListener('input', (event) => {
        event.target.value = event.target.value.replace(/\D/g, '');
    });

    form.querySelectorAll('.salary-input').forEach((input) => {
        input.addEventListener('input', updateSalarySummary);
    });

    form.querySelector('#probation_applicable')?.addEventListener('change', () => {
        toggleProbationFields();
        calculateProbationEndDate(true);
    });

    form.querySelector('#probation_period_months')?.addEventListener('change', () => {
        const endInput = form.querySelector('#probation_end_date');
        if (endInput) {
            delete endInput.dataset.manualEdit;
        }
        calculateProbationEndDate(true);
    });

    form.querySelector('#joining_date')?.addEventListener('change', () => {
        const endInput = form.querySelector('#probation_end_date');
        if (endInput) {
            delete endInput.dataset.manualEdit;
        }
        calculateProbationEndDate(true);
        syncSalaryEffectiveFrom(true);
    });

    form.querySelector('#salary_effective_from')?.addEventListener('input', (event) => {
        event.target.dataset.manualEdit = '1';
    });

    form.querySelector('#shift_id')?.addEventListener('change', () => {
        updateShiftPreview();
        if (currentStep === TOTAL_STEPS) {
            buildReviewSummary();
        }
    });

    form.querySelector('#give_portal_access')?.addEventListener('change', buildReviewSummary);
    form.querySelector('#grant_portal_access')?.addEventListener('change', buildReviewSummary);

    form.querySelector('#probation_end_date')?.addEventListener('input', (event) => {
        event.target.dataset.manualEdit = '1';
        syncProbationStatusFromEndDate();
    });

    form.querySelector('#probation_end_date')?.addEventListener('change', () => {
        syncProbationStatusFromEndDate();
    });

    form.querySelector('#probation_status')?.addEventListener('change', (event) => {
        event.target.dataset.manualStatus = '1';
    });

    form.querySelector('#personal_email')?.addEventListener('blur', () => {
        if (!isSubmitting) {
            validateSyncField('personal_email');
        }
    });

    form.querySelector('#employment_type')?.addEventListener('change', () => {
        const type = getValue('employment_type');
        const probationCheckbox = form.querySelector('#probation_applicable');

        if (!probationCheckbox) {
            return;
        }

        if (['contract', 'intern'].includes(type)) {
            probationCheckbox.checked = false;
        } else {
            probationCheckbox.checked = true;
        }

        toggleProbationFields();
        calculateProbationEndDate(true);
    });

    ['date_of_birth', 'joining_date', 'salary_effective_from', 'probation_end_date'].forEach((field) => {
        form.querySelector(`#${field}`)?.addEventListener('blur', () => {
            if (!isSubmitting) {
                validateSyncField(field);
            }
        });
    });

    prevBtn?.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep -= 1;
            updateStepUi();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    nextBtn?.addEventListener('click', async () => {
        const valid = await validateStep(currentStep);
        if (!valid) {
            showAlert('Please complete all required fields before continuing.');
            focusFirstInvalidField(form);
            return;
        }

        if (currentStep < TOTAL_STEPS) {
            currentStep += 1;
            updateStepUi();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    try {
        setupDateConstraints();
        await Promise.all([loadRoles(), loadShifts()]);
        if (employeeId) {
            await loadManagers(employeeId);
            const { data } = await api.get(`/employees/${employeeId}`);
            await populateForm(data.data.employee);
        } else {
            await loadDepartments();
            await loadManagers();
            form.querySelector('#joining_date').value = maxJoiningDate;
            syncSalaryEffectiveFrom(true);
            toggleProbationFields();
            calculateProbationEndDate(true);
            updatePortalAccessUi();
        }
    } catch (error) {
        showAlert(getErrorMessage(error));
    }

    updateStepUi();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isSubmitting) {
            return;
        }

        for (let step = 1; step <= 3; step += 1) {
            const valid = await validateStep(step);
            if (!valid) {
                currentStep = step;
                updateStepUi();
                showAlert('Please fix errors in the form before saving.');
                focusFirstInvalidField(form);
                return;
            }
        }

        isSubmitting = true;
        setSubmitLoading(submitBtn, true, { isUpdate });

        try {
            const payload = getPayload();
            const response = employeeId
                ? await api.put(`/employees/${employeeId}`, payload)
                : await api.post('/employees', payload);

            const message = response.data.message || 'Employee saved successfully.';
            setFlashMessage(message, flashMessageType(message));
            window.location.href = routes.employeesIndex || '/employees';
        } catch (error) {
            applyBackendErrors(form, error.response?.data?.errors, setFieldError);
            showAlert(getErrorMessage(error));
            isSubmitting = false;
            setSubmitLoading(submitBtn, false, { isUpdate });
            focusFirstInvalidField(form);
        }
    });
});
