import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import {
    bindExpenseRequestViewModal,
    escapeHtml,
    expenseStatusClass,
} from './expense-modals';
import {
    composeActionGroup,
    renderActionGroup,
    renderAddIconButton,
    renderCancelIconButton,
    renderEditIconButton,
    renderViewIconButton,
} from './action-icons';
import { renderApproveIconButton } from './review-actions';

const formatToday = () => new Date().toISOString().slice(0, 10);

document.addEventListener('DOMContentLoaded', async () => {
    const expensesTableBody = document.getElementById('expensesTableBody');
    const groupsTableBody = document.getElementById('groupsTableBody');
    const alertBox = document.getElementById('expensesAlert');
    const paginationInfo = document.getElementById('expensesPaginationInfo');
    const paginationList = document.getElementById('expensesPaginationList');
    const filterApprovalStatus = document.getElementById('filterApprovalStatus');
    const filterBelongsTo = document.getElementById('filterBelongsTo');
    const filterSearch = document.getElementById('filterSearch');
    const filterReset = document.getElementById('filterReset');
    const itemsPerPage = document.getElementById('itemsPerPage');
    const allExpensesPanel = document.getElementById('allExpensesPanel');
    const groupsPanel = document.getElementById('groupsPanel');
    const tabButtons = Array.from(document.querySelectorAll('[data-expenses-tab]'));

    const expenseModalEl = document.getElementById('expenseModal');
    const groupModalEl = document.getElementById('groupModal');
    const groupDetailModalEl = document.getElementById('groupDetailModal');

    if (!expensesTableBody) {
        return;
    }

    let activeTab = 'all';
    let currentPage = 1;
    let expenseTypes = [];
    let draftGroups = [];
    let searchTimeout = null;

    const expenseModal = expenseModalEl ? Modal.getOrCreateInstance(expenseModalEl) : null;
    const groupModal = groupModalEl ? Modal.getOrCreateInstance(groupModalEl) : null;
    const groupDetailModal = groupDetailModalEl ? Modal.getOrCreateInstance(groupDetailModalEl) : null;
    const expenseViewModal = bindExpenseRequestViewModal({
        onError: (message) => showAlert(message, 'danger'),
    });

    const expenseEditingIdInput = document.getElementById('expenseEditingId');
    const expenseModalLabel = document.getElementById('expenseModalLabel');
    const expenseFormDraftBtn = document.getElementById('expenseFormDraftBtn');
    const expenseFormPrimaryBtn = document.getElementById('expenseFormPrimaryBtn');
    const expenseIndependentToggle = document.getElementById('expenseIndependent');
    const expenseGroupSelectWrap = document.getElementById('expenseGroupSelectWrap');
    const expenseGroupSelect = document.getElementById('expenseGroupId');

    const syncExpenseGroupVisibility = async (reloadGroups = false) => {
        if (!expenseIndependentToggle || !expenseGroupSelectWrap) {
            return;
        }

        const isIndependent = expenseIndependentToggle.checked;
        expenseGroupSelectWrap.classList.toggle('d-none', isIndependent);

        if (expenseGroupSelect) {
            expenseGroupSelect.toggleAttribute('required', !isIndependent);
        }

        if (!isIndependent && reloadGroups) {
            try {
                await loadDraftGroups();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        }
    };

    const setExpenseFormMode = (mode, expense = null, options = {}) => {
        const isEdit = mode === 'edit';

        if (expenseEditingIdInput) {
            expenseEditingIdInput.value = isEdit ? String(expense?.id || '') : '';
        }

        if (expenseModalLabel) {
            expenseModalLabel.textContent = isEdit ? 'Edit Expense' : 'Create Expense';
        }

        if (expenseIndependentToggle) {
            expenseIndependentToggle.disabled = isEdit;
            if (isEdit) {
                expenseIndependentToggle.checked = true;
            } else if (options.independent === false) {
                expenseIndependentToggle.checked = false;
            } else {
                expenseIndependentToggle.checked = true;
            }
        }

        syncExpenseGroupVisibility();

        if (expenseFormDraftBtn) {
            expenseFormDraftBtn.classList.toggle('d-none', isEdit || !expense || expense.status !== 'draft');
        }

        if (expenseFormPrimaryBtn) {
            if (isEdit && expense?.status === 'pending') {
                expenseFormPrimaryBtn.textContent = 'Save Changes';
                expenseFormPrimaryBtn.value = 'save';
            } else if (isEdit) {
                expenseFormPrimaryBtn.textContent = 'Save & Submit';
                expenseFormPrimaryBtn.value = 'submit';
            } else {
                expenseFormPrimaryBtn.textContent = 'Create & Submit';
                expenseFormPrimaryBtn.value = 'submit';
            }
        }
    };

    const populateExpenseForm = (expense) => {
        document.getElementById('expenseDate').value = expense.expense_date || '';
        document.getElementById('expenseMerchant').value = expense.merchant || '';
        document.getElementById('expenseTypeId').value = expense.expense_type?.id || '';
        document.getElementById('expenseAmount').value = expense.amount || '';
        document.getElementById('expenseDescription').value = expense.description || '';
        document.getElementById('expenseReference').value = expense.reference_number || '';
        document.getElementById('expenseClaimReimbursement').checked = Boolean(expense.claim_reimbursement);
        document.getElementById('expenseReceipt').value = '';
    };

    const groupEditingIdInput = document.getElementById('groupEditingId');
    const groupModalLabel = document.getElementById('groupModalLabel');
    const groupFormSubmitBtn = document.getElementById('groupFormSubmitBtn');

    const setGroupFormMode = (mode, group = null) => {
        const isEdit = mode === 'edit';

        if (groupEditingIdInput) {
            groupEditingIdInput.value = isEdit ? String(group?.id || '') : '';
        }

        if (groupModalLabel) {
            groupModalLabel.textContent = isEdit ? 'Edit Expense Group' : 'Create Expense Group';
        }

        if (groupFormSubmitBtn) {
            groupFormSubmitBtn.textContent = isEdit ? 'Save Changes' : 'Create';
        }
    };

    const populateGroupForm = (group) => {
        document.getElementById('groupName').value = group.name || '';
        document.getElementById('groupDescription').value = group.description || '';
        document.getElementById('groupFromDate').value = group.from_date || '';
        document.getElementById('groupToDate').value = group.to_date || '';
        document.getElementById('groupTravelAdvance').value = group.travel_advance_amount ?? 0;
    };

    const openGroupForEdit = async (groupId) => {
        if (!groupModal) return;

        try {
            const response = await api.get(`/expense-groups/${groupId}`);
            const group = response.data.data.expense_group;

            if (!group.can_edit) {
                showAlert('This expense group cannot be edited.', 'danger');
                return;
            }

            document.getElementById('groupForm')?.reset();
            populateGroupForm(group);
            setGroupFormMode('edit', group);
            document.getElementById('groupFormAlert')?.classList.add('d-none');
            groupModal.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const openExpenseForEdit = async (expenseId) => {
        if (!expenseModal) return;

        try {
            await loadExpenseTypes();
            const response = await api.get(`/expenses/${expenseId}`);
            const expense = response.data.data.expense;

            if (!expense.can_edit) {
                showAlert('This expense cannot be edited.', 'danger');
                return;
            }

            document.getElementById('expenseForm')?.reset();
            populateExpenseForm(expense);
            setExpenseFormMode('edit', expense);
            document.getElementById('expenseFormAlert')?.classList.add('d-none');
            expenseModal.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const filters = () => ({
        status: filterApprovalStatus?.value || '',
        belongs_to: filterBelongsTo?.value || 'myself',
        search: filterSearch?.value.trim() || '',
        per_page: Number(itemsPerPage?.value || 10),
        page: currentPage,
    });

    const renderPagination = (pagination) => {
        if (!paginationInfo || !paginationList) return;

        if (!pagination?.total) {
            paginationInfo.textContent = '0-0 of 0';
            paginationList.innerHTML = '';
            return;
        }

        paginationInfo.textContent = `${pagination.from}-${pagination.to} of ${pagination.total}`;
        paginationList.innerHTML = '';

        const addItem = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            li.innerHTML = `<button type="button" class="page-link">${label}</button>`;
            if (!disabled && !active) {
                li.querySelector('button').addEventListener('click', () => {
                    currentPage = page;
                    loadActiveTab();
                });
            }
            paginationList.appendChild(li);
        };

        addItem('«', 1, pagination.current_page <= 1);
        addItem('‹', pagination.current_page - 1, pagination.current_page <= 1);
        addItem(String(pagination.current_page), pagination.current_page, false, true);
        addItem('›', pagination.current_page + 1, pagination.current_page >= pagination.last_page);
        addItem('»', pagination.last_page, pagination.current_page >= pagination.last_page);
    };

    const loadExpenseTypes = async () => {
        try {
            const response = await api.get('/expense-types/options');
            expenseTypes = response.data.data.expense_types || [];
            const select = document.getElementById('expenseTypeId');
            if (select) {
                select.innerHTML = '<option value="">- Please Select -</option>' + expenseTypes
                    .map((type) => `<option value="${type.id}">${escapeHtml(type.name)}</option>`)
                    .join('');
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadDraftGroups = async () => {
        try {
            const response = await api.get('/expense-groups/draft-options');
            draftGroups = response.data.data.expense_groups || [];
            const select = document.getElementById('expenseGroupId');
            if (select) {
                select.innerHTML = draftGroups.length
                    ? '<option value="">- Please Select -</option>' + draftGroups
                        .map((group) => `<option value="${group.id}">${escapeHtml(group.name)}</option>`)
                        .join('')
                    : '<option value="">No draft groups — create one under Expense Groups first</option>';
            }
        } catch (error) {
            draftGroups = [];
            const select = document.getElementById('expenseGroupId');
            if (select) {
                select.innerHTML = '<option value="">Unable to load expense groups</option>';
            }
            throw error;
        }
    };

    const loadExpenses = async () => {
        expensesTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5">Loading...</td></tr>';

        try {
            const response = await api.get('/expenses', { params: filters() });
            const expenses = response.data.data.expenses || [];
            const pagination = response.data.data.pagination;

            if (!expenses.length) {
                expensesTableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5">No data available.</td></tr>';
                renderPagination(pagination);
                return;
            }

            expensesTableBody.innerHTML = expenses.map((expense) => {
                const receipt = expense.attachments?.length
                    ? `<a href="${escapeHtml(expense.attachments[0].file_url)}" target="_blank" rel="noopener">View</a>`
                    : '—';

                return `<tr>
                    <td>${escapeHtml(expense.expense_date_label || '—')}</td>
                    <td>${escapeHtml(expense.created_at_label || '—')}</td>
                    <td>${escapeHtml(expense.expense_type?.name || '—')}</td>
                    <td>${escapeHtml(expense.amount_label || '—')}</td>
                    <td><span class="company-status-pill ${expenseStatusClass(expense.payout_status)}">${escapeHtml(expense.payout_status_label || '—')}</span></td>
                    <td><span class="company-status-pill ${expenseStatusClass(expense.status)}">${escapeHtml(expense.status_label || '—')}</span></td>
                    <td>${escapeHtml(expense.reviewed_by?.name || '—')}</td>
                    <td>${escapeHtml(expense.employee?.full_name || '—')}</td>
                    <td>${receipt}</td>
                    <td class="text-nowrap">${composeActionGroup({
                        view: renderViewIconButton('data-view-expense', expense.id, 'View expense'),
                        edit: expense.can_edit
                            ? renderEditIconButton('data-edit-expense', expense.id, 'Edit expense')
                            : '',
                        approve: expense.can_submit
                            ? renderApproveIconButton('data-submit-expense', expense.id, 'Submit expense')
                            : '',
                        cancel: expense.can_cancel
                            ? renderCancelIconButton('data-cancel-expense', expense.id, 'Cancel expense')
                            : '',
                    })}</td>
                </tr>`;
            }).join('');

            renderPagination(pagination);
        } catch (error) {
            expensesTableBody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
        }
    };

    const loadGroups = async () => {
        if (!groupsTableBody) return;

        groupsTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">Loading...</td></tr>';

        try {
            const response = await api.get('/expense-groups', { params: filters() });
            const groups = response.data.data.expense_groups || [];
            const pagination = response.data.data.pagination;

            if (!groups.length) {
                groupsTableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">No data available.</td></tr>';
                renderPagination(pagination);
                return;
            }

            groupsTableBody.innerHTML = groups.map((group) => `
                <tr>
                    <td>${escapeHtml(group.name)}</td>
                    <td>${escapeHtml(group.employee?.full_name || '—')} (${escapeHtml(group.employee?.employee_code || '—')})</td>
                    <td>${escapeHtml(group.created_at_label || '—')}</td>
                    <td>${escapeHtml(group.total_amount_label || '—')}</td>
                    <td>${escapeHtml(group.approved_reimbursable_label || '—')}</td>
                    <td>${escapeHtml(group.travel_advance_label || '—')}</td>
                    <td>${escapeHtml(group.net_adjustment_label || '—')}</td>
                    <td><span class="company-status-pill ${expenseStatusClass(group.status)}">${escapeHtml(group.status_label || '—')}</span></td>
                    <td class="text-nowrap">${composeActionGroup({
                        view: renderViewIconButton('data-view-group', group.id, 'View expense group'),
                        edit: group.can_edit
                            ? renderEditIconButton('data-edit-group', group.id, 'Edit expense group')
                            : '',
                        approve: group.can_submit
                            ? renderApproveIconButton('data-submit-group', group.id, 'Submit expense group')
                            : '',
                        cancel: group.can_cancel
                            ? renderCancelIconButton('data-cancel-group', group.id, 'Cancel expense group')
                            : '',
                    })}</td>
                </tr>
            `).join('');

            renderPagination(pagination);
        } catch (error) {
            groupsTableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</td></tr>`;
        }
    };

    const loadActiveTab = () => {
        if (activeTab === 'groups') {
            loadGroups();
        } else {
            loadExpenses();
        }
    };

    const setActiveTab = (tab) => {
        activeTab = tab;
        currentPage = 1;
        tabButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.expensesTab === tab);
        });
        allExpensesPanel?.classList.toggle('d-none', tab !== 'all');
        groupsPanel?.classList.toggle('d-none', tab !== 'groups');
        loadActiveTab();
    };

    const openGroupDetail = async (groupId) => {
        if (!groupDetailModal) return;

        try {
            const response = await api.get(`/expense-groups/${groupId}`);
            const group = response.data.data.expense_group;

            document.getElementById('groupDetailTitle').textContent = group.name;
            document.getElementById('groupDetailSummary').innerHTML = `
                <div class="small text-muted">${escapeHtml(group.from_date_label)} – ${escapeHtml(group.to_date_label)}</div>
                <div>${escapeHtml(group.description || '')}</div>
                <div class="mt-2"><span class="badge ${expenseStatusClass(group.status)}">${escapeHtml(group.status_label)}</span></div>
            `;

            document.getElementById('groupDetailExpenses').innerHTML = (group.expenses || []).map((expense) => {
                const receipt = expense.attachments?.length
                    ? `<a href="${escapeHtml(expense.attachments[0].file_url)}" target="_blank" rel="noopener">View</a>`
                    : '—';
                const editBtn = expense.can_edit
                    ? renderEditIconButton('data-edit-expense', expense.id, 'Edit expense')
                    : '<span class="text-muted">—</span>';

                return `<tr>
                    <td>${escapeHtml(expense.expense_date_label || '—')}</td>
                    <td>${escapeHtml(expense.expense_type?.name || '—')}</td>
                    <td>${escapeHtml(expense.amount_label || '—')}</td>
                    <td>${receipt}</td>
                    <td>${editBtn}</td>
                </tr>`;
            }).join('') || '<tr><td colspan="5" class="text-muted">No expenses added yet.</td></tr>';

            const actions = document.getElementById('groupDetailActions');
            const footerActions = [];

            if (group.can_edit) {
                footerActions.push(renderEditIconButton('data-edit-group', group.id, 'Edit expense group'));
            }
            if (group.can_add_expense) {
                footerActions.push(renderAddIconButton('data-add-to-group', group.id, 'Add expense to group'));
            }
            if (group.can_submit) {
                footerActions.push(renderApproveIconButton('data-submit-group', group.id, 'Submit expense group'));
            }

            actions.innerHTML = footerActions.length
                ? renderActionGroup(footerActions.join(''))
                : '';

            groupDetailModal.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => setActiveTab(button.dataset.expensesTab));
    });

    [filterApprovalStatus, filterBelongsTo, itemsPerPage].forEach((element) => {
        element?.addEventListener('change', () => {
            currentPage = 1;
            loadActiveTab();
        });
    });

    filterSearch?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadActiveTab();
        }, 300);
    });

    filterReset?.addEventListener('click', () => {
        if (filterApprovalStatus) filterApprovalStatus.value = '';
        if (filterBelongsTo) filterBelongsTo.value = 'myself';
        if (filterSearch) filterSearch.value = '';
        if (itemsPerPage) itemsPerPage.value = '10';
        currentPage = 1;
        loadActiveTab();
    });

    document.getElementById('openExpenseModalBtn')?.addEventListener('click', async () => {
        await loadExpenseTypes();
        await loadDraftGroups();
        document.getElementById('expenseForm')?.reset();
        setExpenseFormMode('create');
        document.getElementById('expenseDate').value = formatToday();
        document.getElementById('expenseClaimReimbursement').checked = true;
        document.getElementById('expenseFormAlert')?.classList.add('d-none');
        expenseModal?.show();
    });
    document.getElementById('openGroupModalBtn')?.addEventListener('click', () => {
        document.getElementById('groupForm')?.reset();
        setGroupFormMode('create');
        document.getElementById('groupTravelAdvance').value = '0';
        document.getElementById('groupFromDate').value = formatToday();
        document.getElementById('groupToDate').value = formatToday();
        document.getElementById('groupFormAlert')?.classList.add('d-none');
        groupModal?.show();
    });

    const onIndependentExpenseToggle = async () => {
        await syncExpenseGroupVisibility(true);
    };

    expenseIndependentToggle?.addEventListener('change', onIndependentExpenseToggle);
    expenseIndependentToggle?.addEventListener('input', onIndependentExpenseToggle);

    document.getElementById('expenseForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formAlert = document.getElementById('expenseFormAlert');
        formAlert?.classList.add('d-none');

        const submitter = event.submitter;
        const shouldSubmit = submitter?.value === 'submit';
        const editingExpenseId = expenseEditingIdInput?.value || '';
        const isIndependent = document.getElementById('expenseIndependent').checked;
        const formData = new FormData();

        formData.append('expense_date', document.getElementById('expenseDate').value);
        formData.append('merchant', document.getElementById('expenseMerchant').value);
        formData.append('expense_type_id', document.getElementById('expenseTypeId').value);
        formData.append('amount', document.getElementById('expenseAmount').value);
        formData.append('description', document.getElementById('expenseDescription').value);
        formData.append('reference_number', document.getElementById('expenseReference').value);
        formData.append('claim_reimbursement', document.getElementById('expenseClaimReimbursement').checked ? '1' : '0');

        const receipt = document.getElementById('expenseReceipt').files[0];
        if (receipt) formData.append('receipt', receipt);

        try {
            if (editingExpenseId) {
                await api.put(`/expenses/${editingExpenseId}`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (shouldSubmit && submitter?.value === 'submit') {
                    await api.patch(`/expenses/${editingExpenseId}/submit`);
                }

                expenseModal?.hide();
                showAlert(shouldSubmit && submitter?.value === 'submit'
                    ? 'Expense updated and submitted for approval.'
                    : 'Expense updated.');
            } else {
                if (shouldSubmit) formData.append('submit', '1');

                if (isIndependent) {
                    await api.post('/expenses', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    });
                } else {
                    const groupId = document.getElementById('expenseGroupId').value;
                    if (!groupId) throw new Error('Select a draft expense group or create one first.');
                    await api.post(`/expense-groups/${groupId}/expenses`, formData, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    });
                }

                expenseModal?.hide();
                showAlert(shouldSubmit ? 'Expense submitted for approval.' : 'Expense saved.');
            }

            loadActiveTab();
            loadDraftGroups();
        } catch (error) {
            if (formAlert) {
                formAlert.textContent = getErrorMessage(error);
                formAlert.classList.remove('d-none');
            }
        }
    });

    document.getElementById('groupForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formAlert = document.getElementById('groupFormAlert');
        formAlert?.classList.add('d-none');
        const editingGroupId = groupEditingIdInput?.value || '';

        try {
            const payload = {
                name: document.getElementById('groupName').value,
                description: document.getElementById('groupDescription').value,
                from_date: document.getElementById('groupFromDate').value,
                to_date: document.getElementById('groupToDate').value,
                travel_advance_amount: document.getElementById('groupTravelAdvance').value || 0,
            };

            if (editingGroupId) {
                await api.put(`/expense-groups/${editingGroupId}`, payload);
                groupModal?.hide();
                showAlert('Expense group updated.');
            } else {
                await api.post('/expense-groups', payload);
                groupModal?.hide();
                showAlert('Expense group created. Add expenses and submit when ready.');
                setActiveTab('groups');
            }

            loadActiveTab();
            loadDraftGroups();
        } catch (error) {
            if (formAlert) {
                formAlert.textContent = getErrorMessage(error);
                formAlert.classList.remove('d-none');
            }
        }
    });

    document.body.addEventListener('click', async (event) => {
        const submitExpenseId = event.target.closest('[data-submit-expense]')?.dataset.submitExpense;
        const cancelExpenseId = event.target.closest('[data-cancel-expense]')?.dataset.cancelExpense;
        const viewExpenseId = event.target.closest('[data-view-expense]')?.dataset.viewExpense;
        const editExpenseId = event.target.closest('[data-edit-expense]')?.dataset.editExpense;
        const editGroupId = event.target.closest('[data-edit-group]')?.dataset.editGroup;
        const submitGroupId = event.target.closest('[data-submit-group]')?.dataset.submitGroup;
        const cancelGroupId = event.target.closest('[data-cancel-group]')?.dataset.cancelGroup;
        const viewGroupId = event.target.closest('[data-view-group]')?.dataset.viewGroup;
        const addToGroupBtn = event.target.closest('[data-add-to-group]');

        if (viewExpenseId) {
            expenseViewModal.openExpense(viewExpenseId);
        }

        if (editExpenseId) {
            openExpenseForEdit(editExpenseId);
        }

        if (submitExpenseId) {
            try {
                await api.patch(`/expenses/${submitExpenseId}/submit`);
                showAlert('Expense submitted for approval.');
                loadActiveTab();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        }

        if (cancelExpenseId && window.confirm('Cancel this expense?')) {
            try {
                await api.patch(`/expenses/${cancelExpenseId}/cancel`);
                showAlert('Expense cancelled.');
                loadActiveTab();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        }

        if (editGroupId) {
            groupDetailModal?.hide();
            openGroupForEdit(editGroupId);
        }

        if (submitGroupId) {
            try {
                await api.patch(`/expense-groups/${submitGroupId}/submit`);
                showAlert('Expense group submitted for approval.');
                groupDetailModal?.hide();
                loadActiveTab();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        }

        if (cancelGroupId && window.confirm('Cancel this expense group?')) {
            try {
                await api.patch(`/expense-groups/${cancelGroupId}/cancel`);
                showAlert('Expense group cancelled.');
                loadActiveTab();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        }

        if (viewGroupId) {
            openGroupDetail(viewGroupId);
        }

        if (addToGroupBtn) {
            groupDetailModal?.hide();
            await loadExpenseTypes();
            document.getElementById('expenseForm')?.reset();
            setExpenseFormMode('create', null, { independent: false });
            await syncExpenseGroupVisibility(true);
            document.getElementById('expenseGroupId').value = addToGroupBtn.dataset.addToGroup;
            document.getElementById('expenseDate').value = formatToday();
            document.getElementById('expenseClaimReimbursement').checked = true;
            document.getElementById('expenseFormAlert')?.classList.add('d-none');
            expenseModal?.show();
        }
    });

    document.getElementById('exportExpensesBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('exportExpensesBtn');
        btn.disabled = true;

        try {
            const response = await api.get('/expenses/export', {
                params: filters(),
                responseType: 'blob',
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.download = `expenses-${formatToday()}.csv`;
            link.click();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            btn.disabled = false;
        }
    });

    await loadExpenseTypes();
    try {
        await loadDraftGroups();
    } catch {
        // Dropdown reloads when user switches to group expense mode.
    }
    loadExpenses();
});
