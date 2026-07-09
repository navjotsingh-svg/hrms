import api, { getErrorMessage } from './api';

import { composeActionGroup, renderCancelIconButton, renderViewLink } from './action-icons';

import { renderApproveIconButton, renderRejectIconButton } from './review-actions';

import { cancelRequest, reviewSingleRequest } from './request-review';

import { bindPagination, bindPerPageSelect, getSerialNumber, readPerPage, renderListPagination } from './pagination';

const routes = () => window.HRMS_WEB_ROUTES || {};

const statusClass = (status) => ({

    pending: 'company-status-pill--inactive',

    approved: 'company-status-pill--active',

    rejected: 'company-status-pill--rejected',

    cancelled: 'company-status-pill--cancelled',

}[status] || '');



document.addEventListener('DOMContentLoaded', async () => {

    const tableBody = document.getElementById('wfhTableBody');

    const alertBox = document.getElementById('wfhAlert');

    const filterStatus = document.getElementById('filterStatus');

    const filterYear = document.getElementById('filterYear');

    const filterReset = document.getElementById('filterReset');

    const paginationInfo = document.getElementById('wfhPaginationInfo');

    const paginationList = document.getElementById('wfhPaginationList');

    const perPageSelect = document.getElementById('wfhPerPage');

    const pendingContainer = document.getElementById('wfhPendingContainer');

    const pendingBadge = document.getElementById('wfhPendingBadge');

    const pendingCard = document.getElementById('wfhPendingCard');

    let currentPage = 1;

    let currentPerPage = readPerPage(perPageSelect);

    const currentYear = new Date().getFullYear();



    if (filterYear) {

        filterYear.innerHTML = Array.from({ length: 3 }, (_, i) => currentYear - 1 + i)

            .map((year) => `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`).join('');

    }



    const showAlert = (message, type = 'success') => {

        if (!alertBox) return;

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;

        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;

        alertBox.classList.remove('d-none');

    };



    const renderRow = (item, index, pagination) => {

        const serial = getSerialNumber(index, pagination);

        return `<tr>

            <td>${serial}</td>

            <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>

            <td>${item.dates_label || item.from_date_label || '—'}</td>

            <td>${item.total_days_label || item.total_days}</td>

            <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>

            <td>${composeActionGroup({

                view: renderViewLink(`${routes().wfhShow || '/wfh'}/${item.id}`, 'View WFH request'),

                cancel: item.can_cancel && item.status === 'pending'

                    ? renderCancelIconButton('data-cancel-wfh', item.id, 'Cancel WFH request')

                    : '',

            })}</td>

        </tr>`;

    };



    const renderPending = (requests) => {

        if (!pendingContainer) {

            return;

        }



        if (!requests.length) {

            pendingCard?.classList.add('d-none');

            return;

        }



        pendingCard?.classList.remove('d-none');

        pendingBadge?.classList.remove('d-none');

        if (pendingBadge) {

            pendingBadge.textContent = String(requests.length);

        }



        pendingContainer.innerHTML = requests.map((item) => {

            const viewLink = renderViewLink(`${routes().wfhShow || '/wfh'}/${item.id}`, 'View request');

            const approveAction = item.can_review

                ? renderApproveIconButton('data-approve-wfh', item.id, 'Approve WFH request')

                : '';

            const rejectAction = item.can_review

                ? renderRejectIconButton('data-reject-wfh', item.id, 'Reject WFH request')

                : '';



            return `

                <div class="border rounded p-3 mb-3">

                    <div class="d-flex flex-wrap justify-content-between gap-2">

                        <div class="flex-grow-1">

                            <div class="fw-semibold">${item.employee?.full_name || 'Employee'}</div>

                            <div class="small text-muted">${item.dates_label || item.from_date_label || '—'} · ${item.total_days_label || item.total_days}</div>

                            <div class="small mt-2">${item.reason || ''}</div>

                        </div>

                        ${composeActionGroup({ view: viewLink, approve: approveAction, reject: rejectAction })}

                    </div>

                </div>

            `;

        }).join('');

    };



    const loadPending = async () => {

        if (!pendingContainer) {

            return;

        }



        try {

            const { data } = await api.get('/wfh-requests/pending');

            renderPending(data.data.wfh_requests || []);

        } catch {

            pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending WFH requests.</div>';

        }

    };



    const loadRequests = async (page = 1) => {

        currentPage = page;

        const params = {

            page,

            per_page: currentPerPage,

            year: filterYear?.value || currentYear,

        };



        if (filterStatus?.value) {

            params.status = filterStatus.value;

        }



        try {

            const { data } = await api.get('/wfh-requests', { params });

            const requests = data.data.wfh_requests || [];

            const pagination = data.data.pagination;



            tableBody.innerHTML = requests.length

                ? requests.map((item, i) => renderRow(item, i, pagination)).join('')

                : '<tr><td colspan="6" class="text-center text-muted py-5">No WFH requests found.</td></tr>';



            renderListPagination({

                infoEl: paginationInfo,

                listEl: paginationList,

                perPageSelectEl: perPageSelect,

                pagination,

                itemLabel: 'requests',

                emptyMessage: 'No WFH requests found',

            });

        } catch (error) {

            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;

        }

    };



    const handleReview = async (id, action) => {

        try {

            const message = await reviewSingleRequest(`wfh:${id}`, action);

            if (!message) {

                return;

            }



            showAlert(message);

            await Promise.all([loadRequests(currentPage), loadPending()]);

        } catch (error) {

            showAlert(getErrorMessage(error), 'danger');

        }

    };



    const handleCancel = async (id) => {

        try {

            const message = await cancelRequest(`wfh:${id}`);

            if (message) {

                showAlert(message);

                await Promise.all([loadRequests(currentPage), loadPending()]);

            }

        } catch (error) {

            showAlert(getErrorMessage(error), 'danger');

        }

    };



    filterStatus?.addEventListener('change', () => loadRequests(1));

    filterYear?.addEventListener('change', () => loadRequests(1));

    filterReset?.addEventListener('click', () => {

        if (filterStatus) filterStatus.value = '';

        if (filterYear) filterYear.value = String(currentYear);

        loadRequests(1);

    });

    bindPagination(paginationList, loadRequests);

    bindPerPageSelect(perPageSelect, (perPage) => {

        currentPerPage = perPage;

        loadRequests(1);

    });



    tableBody?.addEventListener('click', async (event) => {

        const cancel = event.target.closest('[data-cancel-wfh]');

        if (cancel) {

            await handleCancel(cancel.dataset.cancelWfh);

        }

    });



    pendingContainer?.addEventListener('click', (event) => {

        const approve = event.target.closest('[data-approve-wfh]');

        const reject = event.target.closest('[data-reject-wfh]');

        if (approve) handleReview(approve.dataset.approveWfh, 'approve');

        if (reject) handleReview(reject.dataset.rejectWfh, 'reject');

    });



    const urlStatus = new URLSearchParams(window.location.search).get('status');

    if (urlStatus && filterStatus) {

        filterStatus.value = urlStatus;

    }



    try {

        await Promise.all([loadRequests(), loadPending()]);

    } catch (error) {

        tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;

    }

});


