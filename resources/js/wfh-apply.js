import api, { getErrorMessage } from './api';

import { applyBackendErrors, clearFormErrors, setSubmitLoading } from './form-utils';

import { compressImageFiles } from './image-compress';



document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('wfhForm');

    const alertBox = document.getElementById('wfhFormAlert');

    const submitBtn = document.getElementById('wfhSubmitBtn');

    const applicationTypeSelect = document.getElementById('wfh_application_type');

    const proofInput = document.getElementById('proofs');

    const proofPreview = document.getElementById('proofPreview');

    const fromDateInput = document.getElementById('from_date');

    const fromDateLabel = document.getElementById('fromDateLabel');

    const toDateInput = document.getElementById('to_date');

    const toDateWrap = document.getElementById('toDateWrap');

    const daysPreview = document.getElementById('wfhDaysPreview');

    let previewTimer = null;

    let lastPreview = null;



    if (!form) {

        return;

    }



    const showAlert = (message, type = 'danger') => {

        alertBox.className = `alert alert-${type}`;

        alertBox.textContent = message;

        alertBox.classList.remove('d-none');

    };



    const isSingleDayApplication = () => applicationTypeSelect?.value === 'single';



    const getFromDate = () => fromDateInput?.value?.trim() ?? '';



    const getToDate = () => (isSingleDayApplication() ? getFromDate() : toDateInput?.value?.trim() ?? '');



    const updateApplicationTypeUi = () => {

        const single = isSingleDayApplication();



        toDateWrap?.classList.toggle('d-none', single);



        if (fromDateLabel) {

            fromDateLabel.innerHTML = single

                ? 'WFH Date <span class="text-danger">*</span>'

                : 'From Date <span class="text-danger">*</span>';

        }



        if (single) {

            if (getFromDate()) {

                toDateInput.value = getFromDate();

            }

        } else if (getFromDate() && !toDateInput.value) {

            toDateInput.value = getFromDate();

        }



        schedulePreview();

    };



    const flattenPreviewErrors = (errors = {}) => Object.values(errors).flat().join(' ');



    const renderDaysPreview = (preview) => {

        if (!daysPreview) {

            return;

        }



        if (!preview) {

            daysPreview.classList.add('d-none');

            daysPreview.textContent = '';

            daysPreview.classList.remove('text-danger', 'text-success');

            return;

        }



        const countLabel = `${preview.working_days} working day(s)`;



        if (!preview.valid) {

            daysPreview.className = 'form-text text-danger';

            daysPreview.textContent = `${flattenPreviewErrors(preview.errors)} (${countLabel} in selected range)`;

            return;

        }



        daysPreview.className = 'form-text text-success';

        daysPreview.textContent = `This request covers ${countLabel}. Weekends and holidays are excluded.`;

    };



    const runPreview = async () => {

        const fromDate = getFromDate();

        const toDate = getToDate();



        if (!fromDate || !toDate) {

            lastPreview = null;

            renderDaysPreview(null);

            return;

        }



        try {

            const { data } = await api.post('/wfh-requests/preview', {

                from_date: fromDate,

                to_date: toDate,

            });

            lastPreview = data.data.preview;

            renderDaysPreview(lastPreview);

        } catch {

            lastPreview = null;

            renderDaysPreview(null);

        }

    };



    const schedulePreview = () => {

        clearTimeout(previewTimer);

        previewTimer = setTimeout(runPreview, 300);

    };



    applicationTypeSelect?.addEventListener('change', updateApplicationTypeUi);

    fromDateInput?.addEventListener('change', () => {

        if (isSingleDayApplication()) {

            toDateInput.value = getFromDate();

        } else if (!toDateInput.value || toDateInput.value < getFromDate()) {

            toDateInput.value = getFromDate();

        }

        schedulePreview();

    });

    toDateInput?.addEventListener('change', schedulePreview);



    proofInput?.addEventListener('change', () => {

        const files = Array.from(proofInput.files || []);

        proofPreview.textContent = files.length

            ? `${files.length} file(s) selected: ${files.map((file) => file.name).join(', ')}`

            : '';

    });



    form.addEventListener('submit', async (event) => {

        event.preventDefault();

        clearFormErrors(form);



        const fromDate = getFromDate();

        const toDate = getToDate();



        if (!fromDate || !toDate) {

            showAlert('Please select the required date(s).');

            return;

        }



        await runPreview();



        if (lastPreview && !lastPreview.valid) {

            applyBackendErrors(form, { response: { data: { errors: lastPreview.errors } } });

            showAlert(flattenPreviewErrors(lastPreview.errors));

            return;

        }



        const files = Array.from(proofInput?.files || []);



        try {

            setSubmitLoading(submitBtn, true, { submittingText: 'Compressing files...' });

            const preparedFiles = await compressImageFiles(files);



            const formData = new FormData();

            formData.append('from_date', fromDate);

            formData.append('to_date', toDate);

            formData.append('reason', form.querySelector('#reason').value.trim());

            preparedFiles.forEach((file) => formData.append('proofs[]', file));



            setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

            const { data } = await api.post('/wfh-requests', formData, {

                headers: { 'Content-Type': 'multipart/form-data' },

            });

            window.location.href = `${window.HRMS_WEB_ROUTES?.wfhShow || '/wfh'}/${data.data.wfh_request.id}`;

        } catch (error) {

            applyBackendErrors(form, error);

            showAlert(getErrorMessage(error));

        } finally {

            setSubmitLoading(submitBtn, false);

        }

    });



    updateApplicationTypeUi();

});


