import api, { getErrorMessage } from './api';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { promptRequestReviewRemarks } from './swal-utils';

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    cleared: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    returned: 'company-status-pill--active',
    waived: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    paid: 'company-status-pill--active',
    draft: 'company-status-pill--inactive',
    in_progress: 'company-status-pill--inactive',
    completed: 'company-status-pill--active',
}[status] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('offboardingShowCard');
    const content = document.getElementById('offboardingShowContent');
    const alertBox = document.getElementById('offboardingShowAlert');
    const exitCaseId = card?.dataset.exitCaseId;

    if (!card || !exitCaseId) return;

    let currentCase = null;

    const showAlert = (message, type = 'success') => {
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderClearanceTable = (item) => {
        const rows = (item.clearance_items || []).map((row) => `
            <tr>
                <td><input type="checkbox" class="form-check-input clearance-select" value="${row.id}" ${row.can_review ? '' : 'disabled'}></td>
                <td class="fw-semibold">${row.label}</td>
                <td><span class="company-status-pill ${statusClass(row.status)}">${row.status_label}</span></td>
                <td class="small">${row.review_notes || '—'}</td>
                <td class="text-end">${row.can_review ? `
                    <div class="table-action-group">
                        ${renderApproveIconButton('data-clear-item', `${item.id}:${row.id}`, 'Clear')}
                        ${renderRejectIconButton('data-reject-clear-item', `${item.id}:${row.id}`, 'Reject clearance')}
                    </div>` : '—'}</td>
            </tr>
        `).join('');

        return `
            <div class="content-card mb-4">
                <div class="content-card-header border-bottom d-flex justify-content-between align-items-center">
                    <h2 class="content-card-title mb-0">Department Clearance</h2>
                    ${item.can_review_clearance ? `<div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bulk-clear="${item.id}">Clear Selected</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bulk-reject-clear="${item.id}">Reject Selected</button>
                    </div>` : ''}
                </div>
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead><tr><th></th><th>Department</th><th>Status</th><th>Remarks</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="5" class="text-center text-muted py-4">No clearance items.</td></tr>'}</tbody>
                    </table>
                </div>
            </div>`;
    };

    const renderAssetTable = (item) => {
        const rows = (item.asset_return_items || []).map((row) => `
            <tr>
                <td><input type="checkbox" class="form-check-input asset-return-select" value="${row.id}" ${row.can_manage ? '' : 'disabled'}></td>
                <td class="fw-semibold">${row.asset_name}</td>
                <td><span class="company-status-pill ${statusClass(row.status)}">${row.status_label}</span></td>
                <td class="small">${row.condition_notes || '—'}</td>
                <td class="text-end">${row.can_manage ? `
                    <div class="table-action-group">
                        ${renderApproveIconButton('data-return-asset', `${item.id}:${row.id}`, 'Mark returned')}
                        ${renderRejectIconButton('data-waive-asset', `${item.id}:${row.id}`, 'Waive asset')}
                    </div>` : '—'}</td>
            </tr>
        `).join('');

        return `
            <div class="content-card mb-4">
                <div class="content-card-header border-bottom d-flex justify-content-between align-items-center">
                    <h2 class="content-card-title mb-0">Asset Return</h2>
                    ${item.can_manage ? `<div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bulk-return="${item.id}">Mark Selected Returned</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bulk-waive="${item.id}">Waive Selected</button>
                    </div>` : ''}
                </div>
                <div class="table-responsive">
                    <table class="companies-table table mb-0">
                        <thead><tr><th></th><th>Asset</th><th>Status</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>${rows || '<tr><td colspan="5" class="text-center text-muted py-4">No assigned assets to return.</td></tr>'}</tbody>
                    </table>
                </div>
            </div>`;
    };

    const renderSurveyField = (question) => {
        const requiredMark = question.is_required ? '<span class="text-danger">*</span>' : '';
        const requiredAttr = question.is_required ? 'required' : '';
        const id = `survey-q-${question.id}`;

        switch (question.type) {
            case 'text':
                return `<div class="mb-3">
                    <label class="form-label" for="${id}">${question.question} ${requiredMark}</label>
                    <input type="text" class="form-control survey-answer" id="${id}" data-question-id="${question.id}" maxlength="500" ${requiredAttr}>
                </div>`;
            case 'rating':
                return `<div class="mb-3">
                    <label class="form-label" for="${id}">${question.question} ${requiredMark}</label>
                    <select class="form-select survey-answer" id="${id}" data-question-id="${question.id}" ${requiredAttr}>
                        <option value="">Select rating</option>
                        ${[1, 2, 3, 4, 5].map((value) => `<option value="${value}">${value}</option>`).join('')}
                    </select>
                </div>`;
            case 'yes_no':
                return `<div class="mb-3">
                    <label class="form-label">${question.question} ${requiredMark}</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input survey-answer" type="radio" name="survey-${question.id}" id="${id}-yes" data-question-id="${question.id}" value="Yes" ${requiredAttr}>
                            <label class="form-check-label" for="${id}-yes">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input survey-answer" type="radio" name="survey-${question.id}" id="${id}-no" data-question-id="${question.id}" value="No">
                            <label class="form-check-label" for="${id}-no">No</label>
                        </div>
                    </div>
                </div>`;
            case 'select':
                return `<div class="mb-3">
                    <label class="form-label" for="${id}">${question.question} ${requiredMark}</label>
                    <select class="form-select survey-answer" id="${id}" data-question-id="${question.id}" ${requiredAttr}>
                        <option value="">Select an option</option>
                        ${(question.options || []).map((option) => `<option value="${option}">${option}</option>`).join('')}
                    </select>
                </div>`;
            default:
                return `<div class="mb-3">
                    <label class="form-label" for="${id}">${question.question} ${requiredMark}</label>
                    <textarea class="form-control survey-answer" id="${id}" data-question-id="${question.id}" rows="3" ${requiredAttr}></textarea>
                </div>`;
        }
    };

    const renderSurveySection = (item) => {
        const survey = item.survey_response;
        const submitted = survey?.is_submitted;

        if (submitted) {
            const answers = (survey.responses || []).map((entry) => `
                <div class="mb-3">
                    <div class="text-muted small">${entry.question}</div>
                    <div>${entry.answer}</div>
                </div>
            `).join('');

            return `<div class="content-card mb-4">
                <div class="content-card-header border-bottom"><h2 class="content-card-title mb-0">Employee Exit Survey</h2></div>
                <div class="content-card-body">${answers}</div>
            </div>`;
        }

        if (!item.is_owner) {
            return `<div class="content-card mb-4">
                <div class="content-card-header border-bottom"><h2 class="content-card-title mb-0">Employee Exit Survey</h2></div>
                <div class="content-card-body text-muted">Waiting for employee to submit the exit survey.</div>
            </div>`;
        }

        const fields = (item.survey_questions || []).map((question) => renderSurveyField(question)).join('');

        return `<div class="content-card mb-4">
            <div class="content-card-header border-bottom"><h2 class="content-card-title mb-0">Employee Exit Survey</h2></div>
            <div class="content-card-body">
                <p class="small text-muted">Share feedback on your experience. Your responses help improve the workplace for everyone.</p>
                <form id="exitSurveyForm">${fields || '<p class="text-muted">No survey questions are configured yet.</p>'}
                    ${fields ? '<button type="submit" class="btn btn-primary" id="exitSurveySubmitBtn">Submit Exit Survey</button>' : ''}
                </form>
            </div>
        </div>`;
    };

    const renderFnfSection = (item) => {
        const fnf = item.full_and_final_settlement;

        if (!item.can_manage_fnf) {
            return `<div class="content-card mb-4">
                <div class="content-card-header border-bottom"><h2 class="content-card-title mb-0">Full &amp; Final Settlement</h2></div>
                <div class="content-card-body text-muted">F&amp;F settlement will be processed by HR/Payroll.</div>
            </div>`;
        }

        const readonly = fnf?.status === 'paid' ? 'readonly' : '';

        return `<div class="content-card mb-4">
            <div class="content-card-header border-bottom d-flex justify-content-between align-items-center">
                <h2 class="content-card-title mb-0">Full &amp; Final Settlement</h2>
                <span class="company-status-pill ${statusClass(fnf?.status)}">${fnf?.status_label || 'Draft'}</span>
            </div>
            <div class="content-card-body">
                <form id="fnfForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Leave Encashment</label>
                        <input type="number" class="form-control" id="leave_encashment" step="0.01" min="0" value="${fnf?.leave_encashment ?? 0}" ${readonly}>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pending Dues</label>
                        <input type="number" class="form-control" id="pending_dues" step="0.01" min="0" value="${fnf?.pending_dues ?? 0}" ${readonly}>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Deductions</label>
                        <input type="number" class="form-control" id="deductions" step="0.01" min="0" value="${fnf?.deductions ?? 0}" ${readonly}>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Net Payable</label>
                        <input type="number" class="form-control" id="net_payable" step="0.01" readonly value="${fnf?.net_payable ?? 0}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Settlement Notes</label>
                        <textarea class="form-control" id="settlement_notes" rows="3" ${readonly}>${fnf?.settlement_notes || ''}</textarea>
                    </div>
                    ${fnf?.status !== 'paid' ? `<div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" id="fnfSaveBtn">Save Settlement</button>
                        ${fnf?.status === 'draft' ? `<button type="button" class="btn btn-outline-success" id="fnfApproveBtn">Approve Settlement</button>` : ''}
                        ${fnf?.status === 'approved' ? `<button type="button" class="btn btn-success" id="fnfPaidBtn">Mark Paid &amp; Complete Offboarding</button>` : ''}
                    </div>` : ''}
                </form>
            </div>
        </div>`;
    };

    const render = (item) => {
        currentCase = item;
        content.innerHTML = `
            <div class="content-card mb-4">
                <div class="content-card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><span class="text-muted">Employee</span><div class="fw-semibold">${item.employee?.full_name || '—'}</div></div>
                        <div class="col-md-4"><span class="text-muted">Last Working Date</span><div class="fw-semibold">${item.last_working_date || '—'}</div></div>
                        <div class="col-md-4"><span class="text-muted">Current Stage</span><div><span class="company-status-pill ${statusClass(item.status)}">${item.stage_label}</span></div></div>
                        <div class="col-12"><span class="text-muted">Resignation Reason</span><div>${item.resignation_request?.reason || '—'}</div></div>
                    </div>
                </div>
            </div>
            ${renderClearanceTable(item)}
            ${renderAssetTable(item)}
            ${renderSurveySection(item)}
            ${renderFnfSection(item)}
        `;

        bindFnfForm(item);
        bindSurveyForm(item);
    };

    const bindFnfForm = (item) => {
        const form = document.getElementById('fnfForm');
        if (!form || !item.can_manage_fnf) return;

        const recalcNet = () => {
            const encash = Number(document.getElementById('leave_encashment')?.value || 0);
            const dues = Number(document.getElementById('pending_dues')?.value || 0);
            const deductions = Number(document.getElementById('deductions')?.value || 0);
            const netEl = document.getElementById('net_payable');
            if (netEl) netEl.value = (encash + dues - deductions).toFixed(2);
        };

        ['leave_encashment', 'pending_dues', 'deductions'].forEach((id) => {
            document.getElementById(id)?.addEventListener('input', recalcNet);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const { data } = await api.patch(`/exit-cases/${exitCaseId}/settlement`, {
                    leave_encashment: document.getElementById('leave_encashment').value,
                    pending_dues: document.getElementById('pending_dues').value,
                    deductions: document.getElementById('deductions').value,
                    settlement_notes: document.getElementById('settlement_notes').value.trim(),
                });
                showAlert(data.message);
                render(data.data.exit_case);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('fnfApproveBtn')?.addEventListener('click', async () => {
            try {
                const { data } = await api.patch(`/exit-cases/${exitCaseId}/settlement/approve`);
                showAlert(data.message);
                render(data.data.exit_case);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('fnfPaidBtn')?.addEventListener('click', async () => {
            try {
                const { data } = await api.patch(`/exit-cases/${exitCaseId}/settlement/paid`);
                showAlert(data.message);
                render(data.data.exit_case);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    };

    const bindSurveyForm = (item) => {
        document.getElementById('exitSurveyForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const responses = {};
            document.querySelectorAll('.survey-answer').forEach((el) => {
                const questionId = el.dataset.questionId;
                if (!questionId) return;
                if (el.type === 'radio') {
                    if (el.checked) responses[questionId] = el.value.trim();
                    return;
                }
                responses[questionId] = el.value.trim();
            });

            try {
                const { data } = await api.post(`/exit-cases/${exitCaseId}/survey`, { responses });
                showAlert(data.message);
                render(data.data.exit_case);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    };

    const reviewClearance = async (caseId, itemIds, action) => {
        const notes = action === 'reject' ? await promptRequestReviewRemarks({ action: 'reject', count: itemIds.length }) : await promptRequestReviewRemarks({ action: 'approve', count: itemIds.length });
        if (notes === null) return;

        const payload = { action, item_ids: itemIds };
        if (notes?.trim()) payload.notes = notes.trim();

        const { data } = await api.post(`/exit-cases/${caseId}/clearance/review`, payload);
        showAlert(data.message);
        render(data.data.exit_case);
    };

    const reviewAssets = async (caseId, itemIds, action) => {
        const notes = await promptRequestReviewRemarks({ action: 'approve', count: itemIds.length });
        if (notes === null) return;

        const payload = { action, item_ids: itemIds };
        if (notes?.trim()) payload.notes = notes.trim();

        const { data } = await api.post(`/exit-cases/${caseId}/assets/review`, payload);
        showAlert(data.message);
        render(data.data.exit_case);
    };

    card.addEventListener('click', async (event) => {
        const clearItem = event.target.closest('[data-clear-item]');
        const rejectClear = event.target.closest('[data-reject-clear-item]');
        const returnAsset = event.target.closest('[data-return-asset]');
        const waiveAsset = event.target.closest('[data-waive-asset]');
        const bulkClear = event.target.closest('[data-bulk-clear]');
        const bulkRejectClear = event.target.closest('[data-bulk-reject-clear]');
        const bulkReturn = event.target.closest('[data-bulk-return]');
        const bulkWaive = event.target.closest('[data-bulk-waive]');

        try {
            if (clearItem) {
                const [caseId, itemId] = clearItem.dataset.clearItem.split(':');
                await reviewClearance(caseId, [Number(itemId)], 'clear');
            }

            if (rejectClear) {
                const [caseId, itemId] = rejectClear.dataset.rejectClearItem.split(':');
                await reviewClearance(caseId, [Number(itemId)], 'reject');
            }

            if (returnAsset) {
                const [caseId, itemId] = returnAsset.dataset.returnAsset.split(':');
                await reviewAssets(caseId, [Number(itemId)], 'returned');
            }

            if (waiveAsset) {
                const [caseId, itemId] = waiveAsset.dataset.waiveAsset.split(':');
                await reviewAssets(caseId, [Number(itemId)], 'waived');
            }

            if (bulkClear) {
                const ids = Array.from(document.querySelectorAll('.clearance-select:checked')).map((el) => Number(el.value));
                if (!ids.length) return showAlert('Select clearance items first.', 'danger');
                await reviewClearance(bulkClear.dataset.bulkClear, ids, 'clear');
            }

            if (bulkRejectClear) {
                const ids = Array.from(document.querySelectorAll('.clearance-select:checked')).map((el) => Number(el.value));
                if (!ids.length) return showAlert('Select clearance items first.', 'danger');
                await reviewClearance(bulkRejectClear.dataset.bulkRejectClear, ids, 'reject');
            }

            if (bulkReturn) {
                const ids = Array.from(document.querySelectorAll('.asset-return-select:checked')).map((el) => Number(el.value));
                if (!ids.length) return showAlert('Select assets first.', 'danger');
                await reviewAssets(bulkReturn.dataset.bulkReturn, ids, 'returned');
            }

            if (bulkWaive) {
                const ids = Array.from(document.querySelectorAll('.asset-return-select:checked')).map((el) => Number(el.value));
                if (!ids.length) return showAlert('Select assets first.', 'danger');
                await reviewAssets(bulkWaive.dataset.bulkWaive, ids, 'waived');
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    const load = async () => {
        try {
            const { data } = await api.get(`/exit-cases/${exitCaseId}`);
            render(data.data.exit_case);
        } catch (error) {
            content.innerHTML = `<div class="content-card"><div class="content-card-body text-danger text-center py-5">${getErrorMessage(error)}</div></div>`;
        }
    };

    await load();
});
