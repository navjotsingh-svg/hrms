import api, { getErrorMessage } from './api';
import { composeActionGroup, renderEditIconButton, renderViewLink } from './action-icons';
import { renderApproveIconButton, renderRejectIconButton } from './review-actions';
import { promptRequestReviewRemarks } from './swal-utils';

const routes = () => window.HRMS_WEB_ROUTES || {};

const statusClass = (status) => ({
    pending: 'company-status-pill--inactive',
    approved: 'company-status-pill--active',
    rejected: 'company-status-pill--rejected',
    cancelled: 'company-status-pill--cancelled',
    in_progress: 'company-status-pill--inactive',
    completed: 'company-status-pill--active',
}[status] || '');

document.addEventListener('DOMContentLoaded', async () => {
    const pageRoot = document.getElementById('offboardingIndexRoot');
    const canManage = pageRoot?.dataset.canManage === '1';
    const alertBox = document.getElementById('offboardingIndexAlert');
    const pendingContainer = document.getElementById('resignationPendingContainer');
    const pendingBadge = document.getElementById('resignationPendingBadge');
    const pendingCard = document.getElementById('resignationPendingCard');
    const exitCasesBody = document.getElementById('exitCasesTableBody');
    const resignationBody = document.getElementById('resignationRequestsTableBody');
    const exitPaginationInfo = document.getElementById('exitCasesPaginationInfo');
    const exitPaginationList = document.getElementById('exitCasesPaginationList');
    let exitPage = 1;
    let surveyMeta = null;
    let surveyModal;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const reviewResignation = async (id, action) => {
        const notes = await promptRequestReviewRemarks({ action, count: 1 });
        if (notes === null) return null;

        const payload = notes?.trim() ? { notes: notes.trim() } : {};
        const endpoint = action === 'approve'
            ? `/resignation-requests/${id}/approve`
            : `/resignation-requests/${id}/reject`;

        const { data } = await api.patch(endpoint, payload);
        return data.message;
    };

    const renderPending = (items) => {
        if (!pendingContainer) return;

        if (!items.length) {
            pendingCard?.classList.add('d-none');
            return;
        }

        pendingCard?.classList.remove('d-none');
        pendingBadge?.classList.remove('d-none');
        if (pendingBadge) pendingBadge.textContent = String(items.length);

        pendingContainer.innerHTML = items.map((item) => `
            <div class="border rounded p-3 mb-3">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">${item.employee?.full_name || 'Employee'}</div>
                        <div class="small text-muted">Proposed LWD: ${item.proposed_last_working_date || '—'}</div>
                        <div class="small mt-2">${item.reason || ''}</div>
                    </div>
                    ${composeActionGroup({
                        approve: item.can_review ? renderApproveIconButton('data-approve-resignation', item.id, 'Approve resignation') : '',
                        reject: item.can_review ? renderRejectIconButton('data-reject-resignation', item.id, 'Reject resignation') : '',
                    })}
                </div>
            </div>
        `).join('');
    };

    const loadPending = async () => {
        if (!pendingContainer) return;
        try {
            const { data } = await api.get('/resignation-requests/pending');
            renderPending(data.data.resignation_requests || []);
        } catch {
            pendingContainer.innerHTML = '<div class="text-danger">Unable to load pending resignations.</div>';
        }
    };

    const loadExitCases = async (page = 1) => {
        exitPage = page;
        try {
            const { data } = await api.get('/exit-cases', { params: { page, per_page: 10 } });
            const cases = data.data.exit_cases || [];
            const pagination = data.data.pagination;

            exitCasesBody.innerHTML = cases.length
                ? cases.map((item, index) => {
                    const serial = ((pagination.current_page - 1) * pagination.per_page) + index + 1;
                    return `<tr>
                        <td>${serial}</td>
                        <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
                        <td>${item.last_working_date || '—'}</td>
                        <td>${item.stage_label || '—'}</td>
                        <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
                        <td>${renderViewLink(`${routes().offboardingShow || '/offboarding/cases'}/${item.id}`, 'View exit case')}</td>
                    </tr>`;
                }).join('')
                : '<tr><td colspan="6" class="text-center text-muted py-5">No exit cases found.</td></tr>';

            exitPaginationInfo.textContent = pagination?.total
                ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
                : 'No exit cases found';

            exitPaginationList.innerHTML = pagination?.last_page
                ? Array.from({ length: pagination.last_page }, (_, i) => {
                    const p = i + 1;
                    return `<li class="page-item ${p === pagination.current_page ? 'active' : ''}"><button type="button" class="page-link" data-exit-page="${p}">${p}</button></li>`;
                }).join('')
                : '';
        } catch (error) {
            exitCasesBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const loadResignations = async () => {
        try {
            const params = {};
            const urlStatus = new URLSearchParams(window.location.search).get('status');
            if (urlStatus) params.status = urlStatus;

            const { data } = await api.get('/resignation-requests', { params: { ...params, per_page: 10 } });
            const items = data.data.resignation_requests || [];

            resignationBody.innerHTML = items.length
                ? items.map((item, index) => `<tr>
                    <td>${index + 1}</td>
                    <td>${item.employee?.full_name || '—'}</td>
                    <td>${item.proposed_last_working_date || '—'}</td>
                    <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
                    <td>${item.exit_case?.id ? renderViewLink(`${routes().offboardingShow || '/offboarding/cases'}/${item.exit_case.id}`, 'View exit case') : '—'}</td>
                </tr>`).join('')
                : '<tr><td colspan="5" class="text-center text-muted py-5">No resignation requests found.</td></tr>';
        } catch (error) {
            resignationBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    pendingContainer?.addEventListener('click', async (event) => {
        const approve = event.target.closest('[data-approve-resignation]');
        const reject = event.target.closest('[data-reject-resignation]');

        try {
            if (approve) {
                const message = await reviewResignation(approve.dataset.approveResignation, 'approve');
                if (message) {
                    showAlert(message);
                    await Promise.all([loadPending(), loadExitCases(exitPage), loadResignations()]);
                }
            }

            if (reject) {
                const message = await reviewResignation(reject.dataset.rejectResignation, 'reject');
                if (message) {
                    showAlert(message);
                    await Promise.all([loadPending(), loadResignations()]);
                }
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    exitPaginationList?.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-exit-page]');
        if (btn) loadExitCases(Number(btn.dataset.exitPage));
    });

    const toggleSurveyOptions = () => {
        const type = document.getElementById('exitSurveyQuestionType')?.value;
        document.getElementById('exitSurveyOptionsWrap')?.classList.toggle('d-none', type !== 'select');
    };

    const loadSurveyMeta = async () => {
        if (!canManage) return;
        const { data } = await api.get('/exit-survey-questions/meta');
        surveyMeta = data.data;
        const typeSelect = document.getElementById('exitSurveyQuestionType');
        if (typeSelect) {
            typeSelect.innerHTML = (surveyMeta.types || []).map((item) => `<option value="${item.value}">${item.label}</option>`).join('');
        }
    };

    const loadSurveyQuestions = async () => {
        const tableBody = document.getElementById('exitSurveyQuestionsBody');
        if (!canManage || !tableBody) return;

        try {
            const { data } = await api.get('/exit-survey-questions', { params: { per_page: 50 } });
            const questions = data.data.questions || [];

            tableBody.innerHTML = questions.length
                ? questions.map((item) => `<tr>
                    <td>${item.sort_order}</td>
                    <td>${item.question}</td>
                    <td>${item.type_label}</td>
                    <td>${item.is_required ? 'Yes' : 'No'}</td>
                    <td><span class="company-status-pill ${item.status === 'active' ? 'company-status-pill--active' : 'company-status-pill--inactive'}">${item.status === 'active' ? 'Active' : 'Inactive'}</span></td>
                    <td>${composeActionGroup({
                        edit: renderEditIconButton('data-edit-survey-question', item.id, 'Edit question'),
                    })}</td>
                </tr>`).join('')
                : '<tr><td colspan="6" class="text-center text-muted py-5">No survey questions configured.</td></tr>';

            tableBody.querySelectorAll('[data-edit-survey-question]').forEach((btn) => {
                btn.addEventListener('click', () => openSurveyQuestionModal(Number(btn.dataset.editSurveyQuestion), questions));
            });
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const openSurveyQuestionModal = (questionId = null, cachedQuestions = []) => {
        document.getElementById('exitSurveyQuestionId').value = questionId ? String(questionId) : '';
        document.getElementById('exitSurveyQuestionModalTitle').textContent = questionId ? 'Edit Survey Question' : 'Add Survey Question';
        document.getElementById('exitSurveyQuestionText').value = '';
        document.getElementById('exitSurveyQuestionType').value = surveyMeta?.types?.[0]?.value || 'textarea';
        document.getElementById('exitSurveyQuestionSort').value = '';
        document.getElementById('exitSurveyQuestionStatus').value = 'active';
        document.getElementById('exitSurveyQuestionOptions').value = '';
        document.getElementById('exitSurveyQuestionRequired').checked = true;
        toggleSurveyOptions();

        if (questionId) {
            const question = cachedQuestions.find((item) => Number(item.id) === Number(questionId));
            if (question) {
                document.getElementById('exitSurveyQuestionText').value = question.question;
                document.getElementById('exitSurveyQuestionType').value = question.type;
                document.getElementById('exitSurveyQuestionSort').value = question.sort_order;
                document.getElementById('exitSurveyQuestionStatus').value = question.status;
                document.getElementById('exitSurveyQuestionRequired').checked = !!question.is_required;
                document.getElementById('exitSurveyQuestionOptions').value = (question.options || []).join('\n');
                toggleSurveyOptions();
            }
        }

        surveyModal?.show();
    };

    if (canManage) {
        surveyModal = window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('exitSurveyQuestionModal'));

        document.getElementById('exitSurveyCreateBtn')?.addEventListener('click', () => openSurveyQuestionModal());
        document.getElementById('exitSurveyQuestionType')?.addEventListener('change', toggleSurveyOptions);

        document.getElementById('exitSurveyReseedBtn')?.addEventListener('click', async () => {
            if (!window.confirm('Replace all survey questions with the default set?')) return;
            try {
                const { data } = await api.post('/exit-survey-questions/reseed');
                showAlert(data.message);
                await loadSurveyQuestions();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('exitSurveyQuestionForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const questionId = document.getElementById('exitSurveyQuestionId').value;
            const type = document.getElementById('exitSurveyQuestionType').value;
            const optionsRaw = document.getElementById('exitSurveyQuestionOptions').value;
            const payload = {
                question: document.getElementById('exitSurveyQuestionText').value.trim(),
                type,
                is_required: document.getElementById('exitSurveyQuestionRequired').checked,
                sort_order: document.getElementById('exitSurveyQuestionSort').value
                    ? Number(document.getElementById('exitSurveyQuestionSort').value)
                    : undefined,
                status: document.getElementById('exitSurveyQuestionStatus').value,
            };

            if (type === 'select') {
                payload.options = optionsRaw.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
            }

            try {
                if (questionId) {
                    await api.put(`/exit-survey-questions/${questionId}`, payload);
                } else {
                    await api.post('/exit-survey-questions', payload);
                }
                surveyModal?.hide();
                showAlert('Survey question saved.');
                await loadSurveyQuestions();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    const initTasks = [loadPending(), loadExitCases(), loadResignations()];
    if (canManage) {
        initTasks.push(loadSurveyMeta(), loadSurveyQuestions());
    }
    await Promise.all(initTasks);
});
