import api, { getErrorMessage } from './api';

import { setSubmitLoading } from './form-utils';



document.addEventListener('DOMContentLoaded', async () => {

    const form = document.getElementById('payrollSettingsForm');

    const alertBox = document.getElementById('payrollSettingsAlert');

    const submitBtn = document.getElementById('payrollSettingsSubmitBtn');



    if (!form) {

        return;

    }



    let isSubmitting = false;



    const formatCurrency = (value) => `₹ ${Number(value || 0).toLocaleString('en-IN', {

        minimumFractionDigits: 0,

        maximumFractionDigits: 2,

    })}`;



    const num = (id) => parseFloat(form.querySelector(`#${id}`)?.value) || 0;



    const getSampleMonthlyCtc = () => {

        const annualCtc = num('payrollSettingsSampleCtc');



        return annualCtc > 0 ? annualCtc / 12 : 0;

    };



    const computePreviewAmounts = () => {

        const monthlyCtc = getSampleMonthlyCtc();

        const basicPercent = num('company_basic_salary_percent');

        const hraPercent = num('company_hra_percent');

        const specialPercent = num('company_special_allowance_percent');

        const fixedTotal = num('company_conveyance_allowance')

            + num('company_medical_allowance')

            + num('company_other_allowance');



        const basic = monthlyCtc * basicPercent / 100;

        const hra = monthlyCtc * hraPercent / 100;

        const special = monthlyCtc * specialPercent / 100;



        return {

            basic,

            hra,

            special,

            monthlyGross: basic + hra + special + fixedTotal,

        };

    };



    const updatePreview = () => {

        const amounts = computePreviewAmounts();



        form.querySelector('#payrollSettingsBasicPreview').textContent = formatCurrency(amounts.basic);

        form.querySelector('#payrollSettingsHraPreview').textContent = formatCurrency(amounts.hra);

        form.querySelector('#payrollSettingsSpecialPreview').textContent = formatCurrency(amounts.special);

        form.querySelector('#payrollSettingsMonthlyGrossPreview').textContent = formatCurrency(amounts.monthlyGross);

    };



    const showAlert = (message, type = 'success') => {

        if (!alertBox) {

            return;

        }



        alertBox.className = `alert alert-${type} alert-dismissible fade show`;

        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;

        alertBox.classList.remove('d-none');

    };



    const applySettings = (settings = {}) => {

        form.querySelector('#company_pf_applicable').checked = settings.pf_applicable !== false;

        form.querySelector('#company_esi_applicable').checked = Boolean(settings.esi_applicable);

        form.querySelector('#company_professional_tax_applicable').checked = settings.professional_tax_applicable !== false;

        form.querySelector('#company_basic_salary_percent').value = settings.basic_salary_percent ?? 50;

        form.querySelector('#company_hra_percent').value = settings.hra_percent ?? 40;

        form.querySelector('#company_special_allowance_percent').value = settings.special_allowance_percent ?? 0;

        form.querySelector('#company_conveyance_allowance').value = settings.conveyance_allowance ?? 0;

        form.querySelector('#company_medical_allowance').value = settings.medical_allowance ?? 0;

        form.querySelector('#company_other_allowance').value = settings.other_allowance ?? 0;

        updatePreview();

    };



    form.querySelectorAll('.payroll-settings-input').forEach((input) => {

        input.addEventListener('input', updatePreview);

    });



    try {

        const { data } = await api.get('/payroll-settings');

        applySettings(data.data || {});

    } catch (error) {

        showAlert(getErrorMessage(error), 'danger');

    }



    form.addEventListener('submit', async (event) => {

        event.preventDefault();



        if (isSubmitting) {

            return;

        }



        isSubmitting = true;



        try {

            setSubmitLoading(submitBtn, true, { submittingText: 'Saving...' });

            const payload = {

                pf_applicable: form.querySelector('#company_pf_applicable')?.checked ?? false,

                esi_applicable: form.querySelector('#company_esi_applicable')?.checked ?? false,

                professional_tax_applicable: form.querySelector('#company_professional_tax_applicable')?.checked ?? true,

                basic_salary_percent: num('company_basic_salary_percent'),

                hra_percent: num('company_hra_percent'),

                special_allowance_percent: num('company_special_allowance_percent'),

                conveyance_allowance: num('company_conveyance_allowance'),

                medical_allowance: num('company_medical_allowance'),

                other_allowance: num('company_other_allowance'),

            };

            const { data } = await api.put('/payroll-settings', payload);

            applySettings(data.data || payload);

            showAlert(data.message || 'Company payroll settings updated successfully.');

        } catch (error) {

            showAlert(getErrorMessage(error), 'danger');

        } finally {

            setSubmitLoading(submitBtn, false);

            submitBtn.textContent = 'Save Settings';

            isSubmitting = false;

        }

    });

});

