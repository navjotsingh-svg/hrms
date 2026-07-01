import api, { getErrorMessage } from './api';

import {

    bindAssetItemReviewHandlers,

    itemStatusClass,

    renderAssetItemsTable,

} from './asset-item-review';

import { bindBackButton, buildCategoryReturnUrl, showAutoDismissAlert } from './form-utils';

import { cancelRequest } from './request-review';



document.addEventListener('DOMContentLoaded', async () => {

    const card = document.getElementById('assetRequestShowCard');

    const alertBox = document.getElementById('assetRequestShowAlert');

    const toolbarEl = document.getElementById('assetRequestShowCardToolbar');

    const detailsEl = document.getElementById('assetRequestShowCardDetails');

    const requestId = card?.dataset.assetRequestId;



    if (!card || !requestId) return;



    bindBackButton('assetRequestShowBackBtn', buildCategoryReturnUrl('asset'));



    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        showAutoDismissAlert(alertBox, message, type);
    };



    const renderToolbar = (item) => {

        if (!toolbarEl) {

            return;

        }



        const buttons = [];



        if (item.can_cancel) {

            buttons.push(`<button type="button" class="btn btn-outline-secondary btn-sm" data-cancel-asset-request="${item.id}">Cancel Request</button>`);

        }



        toolbarEl.innerHTML = buttons.length

            ? `<div class="request-show-actions d-flex flex-wrap gap-2 justify-content-end">${buttons.join('')}</div>`

            : '';

        toolbarEl.classList.toggle('d-none', !buttons.length);

    };



    const render = (item) => {

        renderToolbar(item);



        if (detailsEl) {

            detailsEl.innerHTML = `

            <div class="row g-4">

                <div class="col-12">

                    <div class="alert alert-info mb-0 py-2 small">Approved assets are assigned to the employee profile automatically. Each asset can be approved or rejected separately.</div>

                </div>

                <div class="col-md-6"><span class="text-muted">Employee</span><div class="fw-semibold">${item.employee?.full_name || '—'}</div></div>

                <div class="col-md-6"><span class="text-muted">Overall Status</span><div><span class="company-status-pill ${itemStatusClass(item.status)}">${item.status_label}</span></div></div>

                <div class="col-md-6"><span class="text-muted">Applied On</span><div>${item.created_at_label || '—'}</div></div>

                ${item.reviewed_at_label ? `<div class="col-md-6"><span class="text-muted">Last Reviewed On</span><div>${item.reviewed_at_label}</div></div>` : ''}

                <div class="col-12"><span class="text-muted">Reason</span><div>${item.reason || '—'}</div></div>

                ${item.review_notes ? `<div class="col-12"><span class="text-muted">Review Summary</span><div>${item.review_notes}</div></div>` : ''}

                <div class="col-12">

                    <span class="text-muted d-block mb-2">Requested Assets</span>

                    ${renderAssetItemsTable(item, { showCheckboxes: item.can_review })}

                </div>

            </div>

        `;

        }

    };



    const load = async () => {

        try {

            const { data } = await api.get(`/asset-requests/${requestId}`);

            render(data.data.asset_request);

        } catch (error) {

            if (toolbarEl) {

                toolbarEl.innerHTML = '';

                toolbarEl.classList.add('d-none');

            }



            if (detailsEl) {

                detailsEl.innerHTML = `<div class="text-danger py-4 text-center">${getErrorMessage(error)}</div>`;

            }

        }

    };



    bindAssetItemReviewHandlers(card, {

        onSuccess: async (message) => {

            showAlert(message);

            await load();

        },

        onError: (error) => showAlert(getErrorMessage(error), 'danger'),

    });



    card?.addEventListener('click', async (event) => {

        const cancelBtn = event.target.closest('[data-cancel-asset-request]');



        if (!cancelBtn) {

            return;

        }



        try {

            const message = await cancelRequest(`asset:${cancelBtn.dataset.cancelAssetRequest}`);



            if (message) {

                showAlert(message);

                await load();

            }

        } catch (error) {

            showAlert(getErrorMessage(error), 'danger');

        }

    });



    await load();

});


