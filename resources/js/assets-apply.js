import api, { getErrorMessage } from './api';

import { applyBackendErrors, clearFormErrors, setSubmitLoading } from './form-utils';

import jQuery from './jquery-select2';

import 'select2/dist/css/select2.min.css';

import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';



document.addEventListener('DOMContentLoaded', async () => {

    const form = document.getElementById('assetRequestForm');

    const alertBox = document.getElementById('assetApplyAlert');

    const submitBtn = document.getElementById('assetRequestSubmitBtn');

    const catalogBody = document.getElementById('assetCatalogBody');

    const assetSelectEl = document.getElementById('asset_type_ids');

    let assetTypes = [];

    let assetSelect = null;



    if (!form || !assetSelectEl) {

        return;

    }



    const routes = () => window.HRMS_WEB_ROUTES || {};



    const showAlert = (message, type = 'danger') => {

        alertBox.className = `alert alert-${type}`;

        alertBox.textContent = message;

        alertBox.classList.remove('d-none');

    };



    const requestableTypes = () => assetTypes.filter((item) => item.can_request);



    const getSelectedAssetIds = () => {

        if (assetSelect?.val) {

            return (assetSelect.val() || []).map((id) => Number(id)).filter(Boolean);

        }



        return Array.from(assetSelectEl.selectedOptions).map((option) => Number(option.value)).filter(Boolean);

    };



    const setSelectedAssetIds = (ids) => {

        const values = [...new Set(ids.map(String))];



        if (assetSelect?.val) {

            assetSelect.val(values).trigger('change.select2Sync');

            return;

        }



        Array.from(assetSelectEl.options).forEach((option) => {

            option.selected = values.includes(option.value);

        });

    };



    const toggleSelectedAsset = (id) => {

        const numId = Number(id);

        const selected = getSelectedAssetIds();



        if (selected.includes(numId)) {

            setSelectedAssetIds(selected.filter((value) => value !== numId));

            return;

        }



        setSelectedAssetIds([...selected, numId]);

    };



    const updateCatalogActions = () => {

        const selectedIds = getSelectedAssetIds();



        catalogBody.querySelectorAll('[data-toggle-asset]').forEach((button) => {

            const assetId = Number(button.dataset.toggleAsset);

            const isSelected = selectedIds.includes(assetId);



            button.textContent = isSelected ? 'Remove' : 'Add';

            button.classList.toggle('btn-outline-danger', isSelected);

            button.classList.toggle('btn-outline-primary', !isSelected);

        });

    };



    const initAssetSelect2 = () => {

        if (typeof jQuery.fn.select2 !== 'function') {

            return;

        }



        if (assetSelect?.hasClass?.('select2-hidden-accessible')) {

            assetSelect.select2('destroy');

        }



        assetSelect = jQuery(assetSelectEl).select2({

            theme: 'bootstrap-5',

            placeholder: assetSelectEl.dataset.placeholder || 'Select one or more assets',

            allowClear: true,

            width: '100%',

            closeOnSelect: false,

            dropdownParent: jQuery(assetSelectEl).closest('.asset-request-form'),

        });



        assetSelect.on('change.select2Sync', () => {

            updateCatalogActions();

        });

    };



    const statusBadge = (item) => {

        if (item.is_assigned) {

            return '<span class="company-status-pill company-status-pill--active">Assigned</span>';

        }



        if (item.has_pending_request) {

            return '<span class="company-status-pill company-status-pill--inactive">Pending</span>';

        }



        return '<span class="company-status-pill">Available</span>';

    };



    const renderCatalog = () => {

        catalogBody.innerHTML = assetTypes.length

            ? assetTypes.map((item) => `

                <tr>

                    <td class="fw-semibold">${item.name}</td>

                    <td>${statusBadge(item)}</td>

                    <td class="text-end">

                        ${item.can_request

                            ? `<button type="button" class="btn btn-sm btn-outline-primary" data-toggle-asset="${item.id}">Add</button>`

                            : '<span class="text-muted small">—</span>'}

                    </td>

                </tr>

            `).join('')

            : '<tr><td colspan="3" class="text-center text-muted py-4">No assets configured for your company.</td></tr>';



        const requestable = requestableTypes();

        assetSelectEl.innerHTML = requestable.map((item) => `<option value="${item.id}">${item.name}</option>`).join('');



        initAssetSelect2();

        updateCatalogActions();



        if (!requestable.length) {

            submitBtn.disabled = true;

            assetSelect?.prop('disabled', true);

        } else {

            submitBtn.disabled = false;

            assetSelect?.prop('disabled', false);

        }

    };



    catalogBody?.addEventListener('click', (event) => {

        const button = event.target.closest('[data-toggle-asset]');



        if (!button) {

            return;

        }



        toggleSelectedAsset(button.dataset.toggleAsset);

        document.getElementById('reason')?.focus();

    });



    form.addEventListener('submit', async (event) => {

        event.preventDefault();

        clearFormErrors(form);

        alertBox.classList.add('d-none');



        const assetTypeIds = getSelectedAssetIds();



        if (!assetTypeIds.length) {

            showAlert('Select at least one asset to request.');

            return;

        }



        try {

            setSubmitLoading(submitBtn, true, { submittingText: 'Submitting...' });

            const { data } = await api.post('/asset-requests', {

                asset_type_ids: assetTypeIds,

                reason: form.querySelector('#reason').value.trim(),

            });



            const requestId = data.data?.asset_request?.id;

            const showUrl = routes().assetRequestsShow || '/asset-requests';



            window.location.href = requestId

                ? `${showUrl}/${requestId}`

                : `${routes().assetRequestsIndex || '/asset-requests'}?status=pending`;

        } catch (error) {

            applyBackendErrors(form, error);

            showAlert(getErrorMessage(error));

        } finally {

            setSubmitLoading(submitBtn, false);

        }

    });



    try {

        const { data } = await api.get('/asset-types/options');

        assetTypes = data.data.asset_types || [];

        renderCatalog();

    } catch (error) {

        catalogBody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">${getErrorMessage(error)}</td></tr>`;

        showAlert(getErrorMessage(error));

    }

});


