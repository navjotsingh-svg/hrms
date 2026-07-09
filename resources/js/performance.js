import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { aiSuggestReview } from './ai-tools';
import { bindEmployeeSearchSelect, formatEmployeeLabel } from './employee-autocomplete';
import {
    renderActionGroup,
    renderAddIconButton,
    renderDeleteButton,
    renderEditIconButton,
    renderViewIconButton,
} from './action-icons';
import {
    bindPagination,
    bindPerPageSelect,
    paginateArray,
    readPerPage,
    renderListPagination,
} from './pagination';

const cfg = window.HRMS_PERFORMANCE || {};
const page = cfg.page || 'overview';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const statusPill = (status) => {
    const map = {
        draft: 'secondary',
        active: 'success',
        closed: 'dark',
        archived: 'secondary',
        not_started: 'secondary',
        in_progress: 'warning',
        submitted: 'success',
        completed: 'success',
        cancelled: 'secondary',
        failed: 'danger',
        nominated: 'primary',
        approved: 'success',
        rejected: 'danger',
        finalized: 'dark',
        proposed: 'info',
        pending: 'secondary',
        adjusted: 'warning',
        confirmed: 'success',
        applied: 'success',
    };

    return `<span class="badge bg-${map[status] || 'secondary'}">${escapeHtml(status?.replace(/_/g, ' '))}</span>`;
};

const showAlert = (message, type = 'success') => {
    const alertBox = document.getElementById('performanceAlert');
    if (!alertBox) return;
    alertBox.className = `alert alert-${type} alert-dismissible fade show`;
    alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    alertBox.classList.remove('d-none');
};

const setHeaderAction = (html) => {
    const el = document.getElementById('performanceHeaderActions');
    if (el) el.innerHTML = html;
};

const paginationBoundPrefixes = new Set();

const renderPagination = (prefix, pagination, onPage) => {
    const list = document.getElementById(`${prefix}PaginationList`);
    const perPageSelectEl = document.getElementById(`${prefix}PerPage`);

    renderListPagination({
        infoEl: document.getElementById(`${prefix}PaginationInfo`),
        listEl: list,
        perPageSelectEl,
        pagination,
        emptyMessage: 'No records',
    });

    if (!paginationBoundPrefixes.has(prefix)) {
        paginationBoundPrefixes.add(prefix);
        bindPagination(list, onPage);
        bindPerPageSelect(perPageSelectEl, () => onPage(1));
    }
};

const bindReviewModal = () => {
    const reviewModalEl = document.getElementById('reviewModal');
    if (!reviewModalEl || !cfg.canReview) return null;

    const reviewModal = Modal.getOrCreateInstance(reviewModalEl);

    const openReviewModal = async (reviewId) => {
        const { data } = await api.get(`/performance-reviews/${reviewId}`);
        const review = data.data.review;
        document.getElementById('reviewEditingId').value = review.id;
        document.getElementById('reviewMeta').textContent = `${review.reviewee?.full_name || 'Employee'} — ${review.cycle?.name || ''}`;
        document.getElementById('reviewSummaryNotes').value = review.summary_notes || '';

        const container = document.getElementById('reviewQuestionsContainer');
        container.innerHTML = (review.cycle?.questions || []).map((q) => {
            const answer = review.answers?.find((a) => a.question_id === q.id) || {};
            return `
                <div class="border rounded p-3">
                    <label class="form-label fw-medium">${escapeHtml(q.question)}</label>
                    <select class="form-select mb-2" data-answer-rating="${q.id}">
                        <option value="">Select rating</option>
                        ${[1, 2, 3, 4, 5].map((n) => `<option value="${n}" ${Number(answer.rating) === n ? 'selected' : ''}>${n}</option>`).join('')}
                    </select>
                    <textarea class="form-control" rows="2" data-answer-comment="${q.id}" placeholder="Comment">${escapeHtml(answer.comment || '')}</textarea>
                </div>
            `;
        }).join('');

        reviewModal.show();
    };

    document.getElementById('reviewAiSuggestBtn')?.addEventListener('click', async () => {
        const button = document.getElementById('reviewAiSuggestBtn');
        const employeeName = document.getElementById('reviewMeta')?.textContent?.split('—')[0]?.trim() || '';
        const notes = Array.from(document.querySelectorAll('#reviewQuestionsContainer textarea, #reviewQuestionsContainer input'))
            .map((field) => field.value?.trim())
            .filter(Boolean)
            .join('\n');

        button.disabled = true;
        button.textContent = 'AI working…';

        try {
            const result = await aiSuggestReview({
                employee_name: employeeName,
                prompt: notes || document.getElementById('reviewSummaryNotes')?.value || 'General performance review',
            });
            document.getElementById('reviewSummaryNotes').value = result.comments || '';
        } catch (error) {
            window.alert(getErrorMessage(error));
        } finally {
            button.disabled = false;
            button.textContent = 'AI suggest';
        }
    });

    document.getElementById('reviewForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const reviewId = document.getElementById('reviewEditingId').value;
        const answers = Array.from(document.querySelectorAll('[data-answer-rating]')).map((el) => ({
            question_id: Number(el.dataset.answerRating),
            rating: el.value ? Number(el.value) : null,
            comment: document.querySelector(`[data-answer-comment="${el.dataset.answerRating}"]`)?.value || null,
        }));

        try {
            await api.post(`/performance-reviews/${reviewId}/submit`, {
                summary_notes: document.getElementById('reviewSummaryNotes').value,
                answers,
            });
            reviewModal.hide();
            showAlert('Review submitted.');
            if (page === 'overview' || page === 'reviews') await initOverview();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    return openReviewModal;
};

const initOverview = async () => {
    const body = document.getElementById('overviewReviewsBody');
    if (!body) return;

    try {
        const { data } = await api.get('/performance/overview');
        const overview = data.data.overview;

        document.getElementById('statActiveCycles').textContent = overview.active_cycles;
        document.getElementById('statPendingReviews').textContent = overview.pending_reviews;
        document.getElementById('statActiveGoals').textContent = overview.active_goals;
        document.getElementById('statActivePips').textContent = overview.active_pips;

        if (!overview.my_reviews?.length) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No pending reviews.</td></tr>';
            return;
        }

        body.innerHTML = overview.my_reviews.map((review) => `
            <tr>
                <td>${escapeHtml(review.cycle_name)}</td>
                <td>${escapeHtml(review.reviewee_name)}</td>
                <td>${statusPill(review.status)}</td>
                <td class="text-end">
                    ${review.status !== 'submitted' && cfg.canReview
                        ? `<button type="button" class="btn btn-sm btn-primary" data-open-review="${review.id}">Complete</button>`
                        : '—'}
                </td>
            </tr>
        `).join('');

        const openReview = bindReviewModal();
        body.querySelectorAll('[data-open-review]').forEach((btn) => {
            btn.addEventListener('click', () => openReview?.(btn.dataset.openReview));
        });
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initReviewCycles = async () => {
    const body = document.getElementById('cyclesTableBody');
    if (!body) return;

    const modalEl = document.getElementById('cycleModal');
    const cycleModal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;

    let cycles = [];

    const paginationInfo = document.getElementById('cyclesPaginationInfo');
    const paginationList = document.getElementById('cyclesPaginationList');
    const perPageSelect = document.getElementById('cyclesPerPage');
    let cyclesPaginationBound = false;

    const renderCycleRow = (cycle) => {
        const actions = [];
        if (cfg.canManage) {
            actions.push(renderEditIconButton('data-edit-cycle', cycle.id, 'Edit'));
            if (cycle.status === 'draft') {
                actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" title="Activate" data-activate-cycle="${cycle.id}">&#9654;</button>`);
            }
            if (cycle.status === 'active') {
                actions.push(`<button type="button" class="table-action-btn" title="${cycle.reviews_open ? 'Close reviews' : 'Open reviews'}" data-toggle-reviews="${cycle.id}" data-open="${cycle.reviews_open ? '1' : '0'}">${cycle.reviews_open ? '&#128274;' : '&#128275;'}</button>`);
                actions.push(`<button type="button" class="table-action-btn table-action-btn--reject" title="Close cycle" data-close-cycle="${cycle.id}">&#9632;</button>`);
            }
        }
        return `
            <tr>
                <td>${escapeHtml(cycle.name)}</td>
                <td>${escapeHtml(cycle.period_start)} – ${escapeHtml(cycle.period_end)}</td>
                <td>${statusPill(cycle.status)}</td>
                <td>${cycle.reviews_open ? 'Yes' : 'No'}</td>
                <td>${cycle.pairs_count ?? '—'}</td>
                <td class="text-end">${renderActionGroup(actions)}</td>
            </tr>
        `;
    };

    const renderCyclesPage = (pageNum = 1) => {
        const { items, pagination } = paginateArray(cycles, pageNum, readPerPage(perPageSelect));

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No review cycles found.</td></tr>';
        } else {
            body.innerHTML = items.map(renderCycleRow).join('');
        }

        if (paginationInfo || paginationList || perPageSelect) {
            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                perPageSelectEl: perPageSelect,
                pagination,
                itemLabel: 'cycles',
                emptyMessage: 'No review cycles found',
            });
        }

        if (!cyclesPaginationBound && paginationList) {
            cyclesPaginationBound = true;
            bindPagination(paginationList, renderCyclesPage);
            bindPerPageSelect(perPageSelect, () => renderCyclesPage(1));
        }
    };

    const loadCycles = async () => {
        const status = document.getElementById('cycleStatusFilter')?.value || '';
        const { data } = await api.get('/performance-review-cycles');
        cycles = (data.data.cycles || []).filter((c) => !status || c.status === status);
        renderCyclesPage(1);
    };

    const renderQuestionRows = (container, questions = []) => {
        if (!container) return;
        container.innerHTML = questions.map((q, i) => `
            <div class="border rounded p-2" data-q-index="${i}">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" data-q-field="question" value="${escapeHtml(q.question || '')}" placeholder="Question" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" data-q-field="weight" value="${q.weight ?? 1}" min="0" step="0.1">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-q="${i}">Remove</button>
                    </div>
                </div>
            </div>
        `).join('') || '<p class="text-muted small mb-0">No questions yet.</p>';
    };

    const collectQuestions = (container) => Array.from(container?.querySelectorAll('[data-q-index]') || []).map((row) => ({
        question: row.querySelector('[data-q-field="question"]')?.value?.trim(),
        weight: Number(row.querySelector('[data-q-field="weight"]')?.value || 1),
    })).filter((q) => q.question);

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openCycleModalBtn">+ Create Cycle</button>');

        document.getElementById('openCycleModalBtn')?.addEventListener('click', () => {
            document.getElementById('cycleEditingId').value = '';
            document.getElementById('cycleModalLabel').textContent = 'Create Review Cycle';
            document.getElementById('cycleForm').reset();
            renderQuestionRows(document.getElementById('cycleQuestionsList'), [{ question: '', weight: 1 }]);
            cycleModal?.show();
        });

        document.getElementById('addCycleQuestionBtn')?.addEventListener('click', () => {
            const list = document.getElementById('cycleQuestionsList');
            const current = collectQuestions(list);
            current.push({ question: '', weight: 1 });
            renderQuestionRows(list, current);
        });

        document.getElementById('cycleQuestionsList')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-q]');
            if (!btn) return;
            const list = document.getElementById('cycleQuestionsList');
            const current = collectQuestions(list);
            current.splice(Number(btn.dataset.removeQ), 1);
            renderQuestionRows(list, current);
        });

        document.getElementById('cycleForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('cycleEditingId').value;
            const payload = {
                name: document.getElementById('cycleName').value,
                description: document.getElementById('cycleDescription').value,
                period_start: document.getElementById('cyclePeriodStart').value,
                period_end: document.getElementById('cyclePeriodEnd').value,
                questions: collectQuestions(document.getElementById('cycleQuestionsList')),
            };

            try {
                if (id) {
                    await api.put(`/performance-review-cycles/${id}`, payload);
                } else {
                    await api.post('/performance-review-cycles', payload);
                }
                cycleModal?.hide();
                showAlert('Review cycle saved.');
                await loadCycles();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-cycle]');
        const activateBtn = e.target.closest('[data-activate-cycle]');
        const toggleBtn = e.target.closest('[data-toggle-reviews]');
        const closeBtn = e.target.closest('[data-close-cycle]');

        try {
            if (editBtn) {
                const { data } = await api.get(`/performance-review-cycles/${editBtn.dataset.editCycle}`);
                const cycle = data.data.cycle;
                document.getElementById('cycleEditingId').value = cycle.id;
                document.getElementById('cycleModalLabel').textContent = 'Edit Review Cycle';
                document.getElementById('cycleName').value = cycle.name;
                document.getElementById('cycleDescription').value = cycle.description || '';
                document.getElementById('cyclePeriodStart').value = cycle.period_start;
                document.getElementById('cyclePeriodEnd').value = cycle.period_end;
                renderQuestionRows(document.getElementById('cycleQuestionsList'), cycle.questions?.length ? cycle.questions : [{ question: '', weight: 1 }]);
                cycleModal?.show();
            }

            if (activateBtn) {
                await api.patch(`/performance-review-cycles/${activateBtn.dataset.activateCycle}/activate`);
                showAlert('Cycle activated.');
                await loadCycles();
            }

            if (toggleBtn) {
                const open = toggleBtn.dataset.open !== '1';
                await api.patch(`/performance-review-cycles/${toggleBtn.dataset.toggleReviews}/reviews-open`, { reviews_open: open });
                showAlert(open ? 'Reviews opened.' : 'Reviews closed.');
                await loadCycles();
            }

            if (closeBtn) {
                await api.patch(`/performance-review-cycles/${closeBtn.dataset.closeCycle}/close`);
                showAlert('Cycle closed.');
                await loadCycles();
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    if (cfg.canReview && document.getElementById('reviewModal')) {
        bindReviewModal();
    }

    document.getElementById('cycleStatusFilter')?.addEventListener('change', () => loadCycles().catch((e) => showAlert(getErrorMessage(e), 'danger')));
    document.getElementById('cycleFilterReset')?.addEventListener('click', () => {
        document.getElementById('cycleStatusFilter').value = '';
        loadCycles().catch((e) => showAlert(getErrorMessage(e), 'danger'));
    });

    try {
        await loadCycles();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initQuestionBank = async () => {
    const body = document.getElementById('questionBankTableBody');
    if (!body) return;

    const modalEl = document.getElementById('questionBankModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;

    let questions = [];
    const paginationInfo = document.getElementById('questionBankPaginationInfo');
    const paginationList = document.getElementById('questionBankPaginationList');
    const perPageSelect = document.getElementById('questionBankPerPage');
    let questionBankPaginationBound = false;

    const renderQuestionRow = (q) => `
        <tr>
            <td>${escapeHtml(q.category || '—')}</td>
            <td>${escapeHtml(q.question)}</td>
            <td>${escapeHtml(q.question_type)}</td>
            <td>${q.default_weight}</td>
            <td>${q.is_active ? 'Yes' : 'No'}</td>
            <td class="text-end">${cfg.canManage ? renderActionGroup([
                renderEditIconButton('data-edit-qb', q.id),
                renderDeleteButton('data-delete-qb', q.id),
            ]) : '—'}</td>
        </tr>
    `;

    const renderQuestionsPage = (pageNum = 1) => {
        const { items, pagination } = paginateArray(questions, pageNum, readPerPage(perPageSelect));

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No questions in bank.</td></tr>';
        } else {
            body.innerHTML = items.map(renderQuestionRow).join('');
        }

        if (paginationInfo || paginationList || perPageSelect) {
            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                perPageSelectEl: perPageSelect,
                pagination,
                itemLabel: 'questions',
                emptyMessage: 'No questions in bank',
            });
        }

        if (!questionBankPaginationBound && paginationList) {
            questionBankPaginationBound = true;
            bindPagination(paginationList, renderQuestionsPage);
            bindPerPageSelect(perPageSelect, () => renderQuestionsPage(1));
        }
    };

    const load = async () => {
        const params = {
            category: document.getElementById('qbCategoryFilter')?.value || undefined,
            search: document.getElementById('qbSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/performance-question-bank', { params });
        questions = data.data.questions || [];
        renderQuestionsPage(1);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openQbModalBtn">+ Add Question</button>');

        document.getElementById('openQbModalBtn')?.addEventListener('click', () => {
            document.getElementById('questionBankEditingId').value = '';
            document.getElementById('questionBankModalLabel').textContent = 'Add Question';
            document.getElementById('questionBankForm').reset();
            document.getElementById('qbActive').checked = true;
            modal?.show();
        });

        document.getElementById('questionBankForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('questionBankEditingId').value;
            const payload = {
                category: document.getElementById('qbCategory').value,
                question: document.getElementById('qbQuestion').value,
                question_type: document.getElementById('qbType').value,
                default_weight: Number(document.getElementById('qbWeight').value || 1),
                is_active: document.getElementById('qbActive').checked,
            };

            try {
                if (id) await api.put(`/performance-question-bank/${id}`, payload);
                else await api.post('/performance-question-bank', payload);
                modal?.hide();
                showAlert('Question saved.');
                await load();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        body.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('[data-edit-qb]');
            const deleteBtn = e.target.closest('[data-delete-qb]');

            try {
                if (editBtn) {
                    const { data } = await api.get('/performance-question-bank');
                    const q = (data.data.questions || []).find((item) => String(item.id) === editBtn.dataset.editQb);
                    if (!q) return;
                    document.getElementById('questionBankEditingId').value = q.id;
                    document.getElementById('questionBankModalLabel').textContent = 'Edit Question';
                    document.getElementById('qbCategory').value = q.category || '';
                    document.getElementById('qbQuestion').value = q.question;
                    document.getElementById('qbType').value = q.question_type;
                    document.getElementById('qbWeight').value = q.default_weight;
                    document.getElementById('qbActive').checked = q.is_active;
                    modal?.show();
                }

                if (deleteBtn && window.confirm('Delete this question?')) {
                    await api.delete(`/performance-question-bank/${deleteBtn.dataset.deleteQb}`);
                    showAlert('Question deleted.');
                    await load();
                }
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    ['qbCategoryFilter', 'qbSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load().catch((e) => showAlert(getErrorMessage(e), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initFeedbackForms = async () => {
    const body = document.getElementById('feedbackFormsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('feedbackFormModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;

    let forms = [];
    const paginationInfo = document.getElementById('feedbackFormsPaginationInfo');
    const paginationList = document.getElementById('feedbackFormsPaginationList');
    const perPageSelect = document.getElementById('feedbackFormsPerPage');
    let feedbackFormsPaginationBound = false;

    const renderQuestionRows = (container, questions = []) => {
        container.innerHTML = questions.map((q, i) => `
            <div class="border rounded p-2" data-fq-index="${i}">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" data-fq-field="question" value="${escapeHtml(q.question || '')}" required>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" data-fq-field="question_type">
                            <option value="rating" ${q.question_type === 'rating' ? 'selected' : ''}>Rating</option>
                            <option value="text" ${q.question_type === 'text' ? 'selected' : ''}>Text</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-fq="${i}">Remove</button>
                    </div>
                </div>
            </div>
        `).join('') || '<p class="text-muted small mb-0">No questions yet.</p>';
    };

    const collectQuestions = (container) => Array.from(container?.querySelectorAll('[data-fq-index]') || []).map((row) => ({
        question: row.querySelector('[data-fq-field="question"]')?.value?.trim(),
        question_type: row.querySelector('[data-fq-field="question_type"]')?.value || 'rating',
    })).filter((q) => q.question);

    const renderFormRow = (form) => `
        <tr>
            <td>${escapeHtml(form.name)}</td>
            <td>${statusPill(form.status)}</td>
            <td>${form.questions_count ?? 0}</td>
            <td>${escapeHtml(form.updated_at?.slice(0, 10) || '—')}</td>
            <td class="text-end">${cfg.canManage ? renderActionGroup([
                renderEditIconButton('data-edit-form', form.id),
                renderDeleteButton('data-delete-form', form.id),
            ]) : '—'}</td>
        </tr>
    `;

    const renderFormsPage = (pageNum = 1) => {
        const { items, pagination } = paginateArray(forms, pageNum, readPerPage(perPageSelect));

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No feedback forms found.</td></tr>';
        } else {
            body.innerHTML = items.map(renderFormRow).join('');
        }

        if (paginationInfo || paginationList || perPageSelect) {
            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                perPageSelectEl: perPageSelect,
                pagination,
                itemLabel: 'forms',
                emptyMessage: 'No feedback forms found',
            });
        }

        if (!feedbackFormsPaginationBound && paginationList) {
            feedbackFormsPaginationBound = true;
            bindPagination(paginationList, renderFormsPage);
            bindPerPageSelect(perPageSelect, () => renderFormsPage(1));
        }
    };

    const load = async () => {
        const params = {
            status: document.getElementById('formStatusFilter')?.value || undefined,
            search: document.getElementById('formSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/performance-feedback-forms', { params });
        forms = data.data.forms || [];
        renderFormsPage(1);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openFeedbackFormBtn">+ Create Form</button>');

        document.getElementById('openFeedbackFormBtn')?.addEventListener('click', () => {
            document.getElementById('feedbackFormEditingId').value = '';
            document.getElementById('feedbackFormModalLabel').textContent = 'Create Feedback Form';
            document.getElementById('feedbackFormForm').reset();
            renderQuestionRows(document.getElementById('feedbackQuestionsList'), [{ question: '', question_type: 'rating' }]);
            modal?.show();
        });

        document.getElementById('addFeedbackQuestionBtn')?.addEventListener('click', () => {
            const list = document.getElementById('feedbackQuestionsList');
            const current = collectQuestions(list);
            current.push({ question: '', question_type: 'rating' });
            renderQuestionRows(list, current);
        });

        document.getElementById('feedbackQuestionsList')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-fq]');
            if (!btn) return;
            const list = document.getElementById('feedbackQuestionsList');
            const current = collectQuestions(list);
            current.splice(Number(btn.dataset.removeFq), 1);
            renderQuestionRows(list, current);
        });

        document.getElementById('feedbackFormForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('feedbackFormEditingId').value;
            const payload = {
                name: document.getElementById('feedbackFormName').value,
                description: document.getElementById('feedbackFormDescription').value,
                status: document.getElementById('feedbackFormStatus').value,
                questions: collectQuestions(document.getElementById('feedbackQuestionsList')),
            };

            try {
                if (id) await api.put(`/performance-feedback-forms/${id}`, payload);
                else await api.post('/performance-feedback-forms', payload);
                modal?.hide();
                showAlert('Feedback form saved.');
                await load();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        body.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('[data-edit-form]');
            const deleteBtn = e.target.closest('[data-delete-form]');

            try {
                if (editBtn) {
                    const { data } = await api.get(`/performance-feedback-forms/${editBtn.dataset.editForm}`);
                    const form = data.data.form;
                    document.getElementById('feedbackFormEditingId').value = form.id;
                    document.getElementById('feedbackFormModalLabel').textContent = 'Edit Feedback Form';
                    document.getElementById('feedbackFormName').value = form.name;
                    document.getElementById('feedbackFormDescription').value = form.description || '';
                    document.getElementById('feedbackFormStatus').value = form.status;
                    renderQuestionRows(document.getElementById('feedbackQuestionsList'), form.questions?.length ? form.questions : [{ question: '', question_type: 'rating' }]);
                    modal?.show();
                }

                if (deleteBtn && window.confirm('Delete this form?')) {
                    await api.delete(`/performance-feedback-forms/${deleteBtn.dataset.deleteForm}`);
                    showAlert('Form deleted.');
                    await load();
                }
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    ['formStatusFilter', 'formSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load().catch((e) => showAlert(getErrorMessage(e), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => load().catch((e) => showAlert(getErrorMessage(e), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initGoals = async () => {
    const body = document.getElementById('goalsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('goalModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;
    let departments = [];

    const levelLabel = (level) => ({
        company: 'Company',
        department: 'Department',
        individual: 'Individual',
    }[level] || level || '—');

    const ownerLabel = (goal) => {
        if (goal.level === 'company') return 'Company-wide';
        if (goal.level === 'department') return goal.department?.name || '—';
        return goal.employee?.full_name || '—';
    };

    const syncGoalLevelFields = () => {
        const level = document.getElementById('goalLevel')?.value || 'individual';
        const departmentWrap = document.getElementById('goalDepartmentWrap');
        const employeeWrap = document.getElementById('goalEmployeeWrap');
        const visibility = document.getElementById('goalVisibility');

        departmentWrap?.classList.toggle('d-none', level !== 'department');
        employeeWrap?.classList.toggle('d-none', level !== 'individual');

        if (level === 'company' && visibility) {
            visibility.value = 'company';
        }
    };

    const loadDepartments = async () => {
        if (!cfg.canManage || departments.length) return;

        try {
            const { data } = await api.get('/departments', { params: { per_page: 100, status: 'active' } });
            departments = data.data?.departments || data.data || [];
            const select = document.getElementById('goalDepartmentId');
            if (!select) return;

            select.innerHTML = '<option value="">Select department</option>' + departments.map((department) => (
                `<option value="${department.id}">${escapeHtml(department.name)}</option>`
            )).join('');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const goalEmployeeSearch = bindEmployeeSearchSelect({
        inputId: 'goalEmployeeSearch',
        hiddenId: 'goalEmployeeId',
    });

    const renderKrRows = (container, items = []) => {
        container.innerHTML = items.map((kr, i) => `
            <div class="border rounded p-2" data-kr-index="${i}">
                <div class="row g-2">
                    <div class="col-md-5"><input type="text" class="form-control form-control-sm" data-kr-field="title" value="${escapeHtml(kr.title || '')}" placeholder="Key result title" required></div>
                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" data-kr-field="target_value" value="${kr.target_value ?? 100}" min="0"></div>
                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" data-kr-field="current_value" value="${kr.current_value ?? 0}" min="0"></div>
                    <div class="col-md-2"><input type="text" class="form-control form-control-sm" data-kr-field="unit" value="${escapeHtml(kr.unit || '')}" placeholder="Unit"></div>
                    <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-kr="${i}">×</button></div>
                </div>
            </div>
        `).join('') || '<p class="text-muted small mb-0">No key results yet.</p>';
    };

    const collectKr = (container) => Array.from(container?.querySelectorAll('[data-kr-index]') || []).map((row) => ({
        title: row.querySelector('[data-kr-field="title"]')?.value?.trim(),
        target_value: Number(row.querySelector('[data-kr-field="target_value"]')?.value || 100),
        current_value: Number(row.querySelector('[data-kr-field="current_value"]')?.value || 0),
        unit: row.querySelector('[data-kr-field="unit"]')?.value || null,
    })).filter((kr) => kr.title);

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = {
            page: pageNum,
            per_page: readPerPage(document.getElementById('goalsPerPage')),
            status: document.getElementById('goalStatusFilter')?.value || undefined,
            level: document.getElementById('goalLevelFilter')?.value || undefined,
            search: document.getElementById('goalSearchFilter')?.value || undefined,
            scope: cfg.canManage ? 'all' : undefined,
        };
        const { data } = await api.get('/goals', { params });
        const goals = data.data.goals || [];

        if (!goals.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No goals found.</td></tr>';
            renderPagination('goals', data.data.pagination, load);
            return;
        }

        body.innerHTML = goals.map((goal) => {
            const actions = [renderEditIconButton('data-edit-goal', goal.id)];
            if (cfg.canManage && goal.can_cascade) {
                actions.push(renderAddIconButton(
                    'data-cascade-goal',
                    goal.id,
                    goal.level === 'company' ? 'Cascade to departments' : 'Cascade to employees'
                ));
            }

            return `
            <tr>
                <td>${escapeHtml(goal.title)}</td>
                <td>${escapeHtml(levelLabel(goal.level))}</td>
                <td>${escapeHtml(ownerLabel(goal))}</td>
                <td>${escapeHtml(goal.parent?.title || '—')}</td>
                <td>${escapeHtml(goal.period_start || '—')} – ${escapeHtml(goal.period_end || '—')}</td>
                <td>${goal.progress ?? 0}%</td>
                <td>${statusPill(goal.status)}</td>
                <td class="text-end">${renderActionGroup(actions)}</td>
            </tr>
        `;
        }).join('');

        renderPagination('goals', data.data.pagination, load);
    };

    const resetGoalForm = () => {
        document.getElementById('goalEditingId').value = '';
        document.getElementById('goalForm').reset();
        document.getElementById('goalLevel').value = cfg.canManage ? 'company' : 'individual';
        goalEmployeeSearch?.clearSelection?.();
        syncGoalLevelFields();
        renderKrRows(document.getElementById('keyResultsList'), [{ title: '', target_value: 100, current_value: 0 }]);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openGoalModalBtn">+ Create Goal</button>');
        document.getElementById('goalLevelFieldWrap')?.classList.remove('d-none');
    } else {
        document.getElementById('goalLevelFieldWrap')?.classList.add('d-none');
        setHeaderAction('<button type="button" class="btn btn-primary" id="openGoalModalBtn">+ Create Goal</button>');
    }

    document.getElementById('openGoalModalBtn')?.addEventListener('click', async () => {
        document.getElementById('goalModalLabel').textContent = 'Create Goal';
        resetGoalForm();
        await loadDepartments();
        modal?.show();
    });

    document.getElementById('goalLevel')?.addEventListener('change', syncGoalLevelFields);

    document.getElementById('addKeyResultBtn')?.addEventListener('click', () => {
        const list = document.getElementById('keyResultsList');
        const current = collectKr(list);
        current.push({ title: '', target_value: 100, current_value: 0 });
        renderKrRows(list, current);
    });

    document.getElementById('keyResultsList')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-kr]');
        if (!btn) return;
        const list = document.getElementById('keyResultsList');
        const current = collectKr(list);
        current.splice(Number(btn.dataset.removeKr), 1);
        renderKrRows(list, current);
    });

    document.getElementById('goalForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('goalEditingId').value;
        const level = document.getElementById('goalLevel')?.value || 'individual';
        const payload = {
            title: document.getElementById('goalTitle').value,
            description: document.getElementById('goalDescription').value,
            period_start: document.getElementById('goalPeriodStart').value || null,
            period_end: document.getElementById('goalPeriodEnd').value || null,
            status: document.getElementById('goalStatus').value,
            visibility: document.getElementById('goalVisibility').value,
            key_results: collectKr(document.getElementById('keyResultsList')),
        };

        if (!id) {
            payload.level = level;
            if (level === 'department') {
                payload.department_id = Number(document.getElementById('goalDepartmentId')?.value || 0) || null;
            }
            if (level === 'individual') {
                payload.employee_id = Number(document.getElementById('goalEmployeeId')?.value || 0) || null;
            }
        }

        try {
            if (id) await api.put(`/goals/${id}`, payload);
            else await api.post('/goals', payload);
            modal?.hide();
            showAlert('Goal saved.');
            await load(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-goal]');
        if (editBtn) {
            try {
                const { data } = await api.get(`/goals/${editBtn.dataset.editGoal}`);
                const goal = data.data.goal;
                await loadDepartments();
                document.getElementById('goalEditingId').value = goal.id;
                document.getElementById('goalModalLabel').textContent = 'Edit Goal';
                document.getElementById('goalTitle').value = goal.title;
                document.getElementById('goalDescription').value = goal.description || '';
                document.getElementById('goalPeriodStart').value = goal.period_start || '';
                document.getElementById('goalPeriodEnd').value = goal.period_end || '';
                document.getElementById('goalStatus').value = goal.status;
                document.getElementById('goalVisibility').value = goal.visibility;
                document.getElementById('goalLevel').value = goal.level || 'individual';
                document.getElementById('goalDepartmentId').value = goal.department?.id || '';
                if (goal.employee) {
                    document.getElementById('goalEmployeeId').value = goal.employee.id;
                    document.getElementById('goalEmployeeSearch').value = formatEmployeeLabel(goal.employee);
                } else {
                    goalEmployeeSearch?.clearSelection?.();
                }
                syncGoalLevelFields();
                renderKrRows(document.getElementById('keyResultsList'), goal.key_results?.length ? goal.key_results : [{ title: '', target_value: 100, current_value: 0 }]);
                modal?.show();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }

            return;
        }

        const cascadeBtn = e.target.closest('[data-cascade-goal]');
        if (!cascadeBtn) return;

        const goalId = cascadeBtn.dataset.cascadeGoal;
        const confirmMessage = cascadeBtn.title.includes('departments')
            ? 'Create department goals from this company goal for all active departments?'
            : 'Create individual goals from this department goal for all active employees in the department?';

        if (!window.confirm(confirmMessage)) return;

        try {
            const { data } = await api.post(`/goals/${goalId}/cascade`);
            showAlert(data.message || 'Goals cascaded successfully.');
            await load(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    ['goalStatusFilter', 'goalSearchFilter', 'goalLevelFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initKpi = async () => {
    const body = document.getElementById('kpiTableBody');
    if (!body) return;

    const modalEl = document.getElementById('kpiModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const kpiEmployeeSearch = bindEmployeeSearchSelect({
        inputId: 'kpiEmployeeSearch',
        hiddenId: 'kpiEmployeeId',
    });

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = {
            page: pageNum,
            per_page: readPerPage(document.getElementById('kpiPerPage')),
            status: document.getElementById('kpiStatusFilter')?.value || undefined,
            search: document.getElementById('kpiSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/performance-kpis', { params });
        const kpis = data.data.kpis || [];

        if (!kpis.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No KPIs found.</td></tr>';
            renderPagination('kpi', data.data.pagination, load);
            return;
        }

        body.innerHTML = kpis.map((kpi) => `
            <tr>
                <td>${escapeHtml(kpi.title)}</td>
                <td>${escapeHtml(kpi.employee?.full_name || '—')}</td>
                <td>${kpi.target_value}${kpi.unit ? ` ${escapeHtml(kpi.unit)}` : ''}</td>
                <td>${kpi.current_value}</td>
                <td>${kpi.progress_percent}%</td>
                <td>${escapeHtml(kpi.frequency)}</td>
                <td>${statusPill(kpi.status)}</td>
                <td class="text-end">${cfg.canManage ? renderActionGroup([
                    renderEditIconButton('data-edit-kpi', kpi.id),
                    renderDeleteButton('data-delete-kpi', kpi.id),
                ]) : '—'}</td>
            </tr>
        `).join('');

        renderPagination('kpi', data.data.pagination, load);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openKpiModalBtn">+ Create KPI</button>');

        document.getElementById('openKpiModalBtn')?.addEventListener('click', () => {
            document.getElementById('kpiEditingId').value = '';
            document.getElementById('kpiModalLabel').textContent = 'Create KPI';
            document.getElementById('kpiForm').reset();
            kpiEmployeeSearch?.clearSelection();
            modal?.show();
        });

        document.getElementById('kpiForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('kpiEditingId').value;
            const payload = {
                employee_id: Number(document.getElementById('kpiEmployeeId').value),
                title: document.getElementById('kpiTitle').value,
                description: document.getElementById('kpiDescription').value,
                target_value: Number(document.getElementById('kpiTarget').value || 100),
                current_value: Number(document.getElementById('kpiCurrent').value || 0),
                unit: document.getElementById('kpiUnit').value,
                frequency: document.getElementById('kpiFrequency').value,
                period_start: document.getElementById('kpiPeriodStart').value || null,
                period_end: document.getElementById('kpiPeriodEnd').value || null,
                status: document.getElementById('kpiFormStatus').value,
            };

            try {
                if (id) await api.put(`/performance-kpis/${id}`, payload);
                else await api.post('/performance-kpis', payload);
                modal?.hide();
                showAlert('KPI saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        body.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('[data-edit-kpi]');
            const deleteBtn = e.target.closest('[data-delete-kpi]');

            try {
                if (editBtn) {
                    const { data } = await api.get(`/performance-kpis/${editBtn.dataset.editKpi}`);
                    const kpi = data.data.kpi;
                    document.getElementById('kpiEditingId').value = kpi.id;
                    document.getElementById('kpiModalLabel').textContent = 'Edit KPI';
                    document.getElementById('kpiTitle').value = kpi.title;
                    document.getElementById('kpiDescription').value = kpi.description || '';
                    kpiEmployeeSearch?.setSelection(kpi.employee?.id ? {
                        id: kpi.employee.id,
                        label: formatEmployeeLabel(kpi.employee),
                    } : null);
                    document.getElementById('kpiTarget').value = kpi.target_value;
                    document.getElementById('kpiCurrent').value = kpi.current_value;
                    document.getElementById('kpiUnit').value = kpi.unit || '';
                    document.getElementById('kpiFrequency').value = kpi.frequency;
                    document.getElementById('kpiPeriodStart').value = kpi.period_start || '';
                    document.getElementById('kpiPeriodEnd').value = kpi.period_end || '';
                    document.getElementById('kpiFormStatus').value = kpi.status;
                    modal?.show();
                }

                if (deleteBtn && window.confirm('Delete this KPI?')) {
                    await api.delete(`/performance-kpis/${deleteBtn.dataset.deleteKpi}`);
                    showAlert('KPI deleted.');
                    await load(currentPage);
                }
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    ['kpiStatusFilter', 'kpiSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initPip = async () => {
    const body = document.getElementById('pipTableBody');
    if (!body) return;

    const modalEl = document.getElementById('pipModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    let pipEmployeeSearch = null;

    if (document.getElementById('pipEmployeeSearch')) {
        pipEmployeeSearch = bindEmployeeSearchSelect({
            inputId: 'pipEmployeeSearch',
            hiddenId: 'pipEmployeeId',
        });
    }

    const renderKrRows = (container, items = []) => {
        container.innerHTML = items.map((kr, i) => `
            <div class="border rounded p-2" data-pkr-index="${i}">
                <div class="row g-2">
                    <div class="col-md-7"><input type="text" class="form-control form-control-sm" data-pkr-field="title" value="${escapeHtml(kr.title || '')}" placeholder="Milestone" required></div>
                    <div class="col-md-3"><input type="date" class="form-control form-control-sm" data-pkr-field="target_date" value="${kr.target_date || ''}"></div>
                    <div class="col-md-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-pkr="${i}">×</button></div>
                </div>
            </div>
        `).join('') || '<p class="text-muted small mb-0">No milestones yet.</p>';
    };

    const collectKr = (container) => Array.from(container?.querySelectorAll('[data-pkr-index]') || []).map((row) => ({
        title: row.querySelector('[data-pkr-field="title"]')?.value?.trim(),
        target_date: row.querySelector('[data-pkr-field="target_date"]')?.value || null,
    })).filter((kr) => kr.title);

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = {
            page: pageNum,
            per_page: readPerPage(document.getElementById('pipPerPage')),
            status: document.getElementById('pipStatusFilter')?.value || undefined,
            search: document.getElementById('pipSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/pips', { params });
        const pips = data.data.pips || [];

        if (!pips.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No PIPs found.</td></tr>';
            renderPagination('pip', data.data.pagination, load);
            return;
        }

        body.innerHTML = pips.map((pip) => `
            <tr>
                <td>${escapeHtml(pip.title)}</td>
                <td>${escapeHtml(pip.employee?.full_name || '—')}</td>
                <td>${escapeHtml(pip.manager?.full_name || '—')}</td>
                <td>${escapeHtml(pip.start_date)} – ${escapeHtml(pip.end_date)}</td>
                <td>${statusPill(pip.status)}</td>
                <td class="text-end">${cfg.canManagePips ? renderActionGroup([
                    renderEditIconButton('data-edit-pip', pip.id),
                    pip.status === 'draft' ? `<button type="button" class="table-action-btn table-action-btn--approve" title="Activate" data-activate-pip="${pip.id}">&#9654;</button>` : '',
                ].filter(Boolean)) : '—'}</td>
            </tr>
        `).join('');

        renderPagination('pip', data.data.pagination, load);
    };

    if (cfg.canManagePips) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openPipModalBtn">+ Create PIP</button>');

        document.getElementById('openPipModalBtn')?.addEventListener('click', () => {
            document.getElementById('pipEditingId').value = '';
            document.getElementById('pipModalLabel').textContent = 'Create PIP';
            document.getElementById('pipForm').reset();
            pipEmployeeSearch?.clearSelection();
            renderKrRows(document.getElementById('pipKeyResultsList'), [{ title: '', target_date: '' }]);
            modal?.show();
        });

        document.getElementById('addPipKrBtn')?.addEventListener('click', () => {
            const list = document.getElementById('pipKeyResultsList');
            const current = collectKr(list);
            current.push({ title: '', target_date: '' });
            renderKrRows(list, current);
        });

        document.getElementById('pipKeyResultsList')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-pkr]');
            if (!btn) return;
            const list = document.getElementById('pipKeyResultsList');
            const current = collectKr(list);
            current.splice(Number(btn.dataset.removePkr), 1);
            renderKrRows(list, current);
        });

        document.getElementById('pipForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('pipEditingId').value;
            const payload = {
                employee_id: Number(document.getElementById('pipEmployeeId').value),
                title: document.getElementById('pipTitle').value,
                reason: document.getElementById('pipReason').value,
                start_date: document.getElementById('pipStartDate').value,
                end_date: document.getElementById('pipEndDate').value,
                key_results: collectKr(document.getElementById('pipKeyResultsList')),
            };

            try {
                if (id) await api.put(`/pips/${id}`, payload);
                else await api.post('/pips', payload);
                modal?.hide();
                showAlert('PIP saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        body.addEventListener('click', async (e) => {
            const editBtn = e.target.closest('[data-edit-pip]');
            const activateBtn = e.target.closest('[data-activate-pip]');

            try {
                if (editBtn) {
                    const { data } = await api.get(`/pips/${editBtn.dataset.editPip}`);
                    const pip = data.data.pip;
                    document.getElementById('pipEditingId').value = pip.id;
                    document.getElementById('pipModalLabel').textContent = 'Edit PIP';
                    document.getElementById('pipTitle').value = pip.title;
                    document.getElementById('pipReason').value = pip.reason || '';
                    pipEmployeeSearch?.setSelection(pip.employee?.id ? {
                        id: pip.employee.id,
                        label: formatEmployeeLabel(pip.employee),
                    } : null);
                    document.getElementById('pipStartDate').value = pip.start_date;
                    document.getElementById('pipEndDate').value = pip.end_date;
                    renderKrRows(document.getElementById('pipKeyResultsList'), pip.key_results?.length ? pip.key_results : [{ title: '', target_date: '' }]);
                    modal?.show();
                }

                if (activateBtn) {
                    await api.patch(`/pips/${activateBtn.dataset.activatePip}/status`, { status: 'active' });
                    showAlert('PIP activated.');
                    await load(currentPage);
                }
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    ['pipStatusFilter', 'pipSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((e) => showAlert(getErrorMessage(e), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initInsights = async () => {
    const body = document.getElementById('insightsReviewsBody');
    if (!body) return;

    try {
        const { data } = await api.get('/performance/overview');
        const overview = data.data.overview;

        document.getElementById('insightsActiveCycles').textContent = overview.active_cycles;
        document.getElementById('insightsPendingReviews').textContent = overview.pending_reviews;
        document.getElementById('insightsActiveGoals').textContent = overview.active_goals;
        document.getElementById('insightsActivePips').textContent = overview.active_pips;

        if (!overview.my_reviews?.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No review data yet.</td></tr>';
            return;
        }

        body.innerHTML = overview.my_reviews.map((review) => `
            <tr>
                <td>${escapeHtml(review.cycle_name)}</td>
                <td>${escapeHtml(review.reviewee_name)}</td>
                <td>${statusPill(review.status)}</td>
            </tr>
        `).join('');
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const formatMoney = (value, currency = 'INR') => {
    if (value === null || value === undefined || value === '') return '—';
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value));
};

const loadReviewCycles = async (selectId) => {
    const select = document.getElementById(selectId);
    if (!select) return;

    const placeholder = selectId === 'calibrationCycleId'
        ? '<option value="">No cycle (manual session)</option>'
        : '<option value="">Select review cycle</option>';

    try {
        const { data } = await api.get('/performance-review-cycles');
        const cycles = data.data?.cycles || [];
        const current = select.value;
        select.innerHTML = placeholder + cycles.map((cycle) => (
            `<option value="${cycle.id}">${escapeHtml(cycle.name)}</option>`
        )).join('');
        if (current) select.value = current;
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initCalibration = async () => {
    const body = document.getElementById('calibrationTableBody');
    if (!body) return;

    const modalEl = document.getElementById('calibrationModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    const detailModalEl = document.getElementById('calibrationDetailModal');
    const detailModal = detailModalEl ? Modal.getOrCreateInstance(detailModalEl) : null;
    let currentPage = 1;
    let activeSessionId = null;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = {
            page: pageNum,
            per_page: readPerPage(document.getElementById('calibrationPerPage')),
            status: document.getElementById('calibrationStatusFilter')?.value || undefined,
            search: document.getElementById('calibrationSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/performance-calibration', { params });
        const sessions = data.data.sessions || [];

        if (!sessions.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No calibration sessions found.</td></tr>';
            renderPagination('calibration', data.data.pagination, load);
            return;
        }

        body.innerHTML = sessions.map((session) => `
            <tr>
                <td>${escapeHtml(session.name)}</td>
                <td>${escapeHtml(session.cycle?.name || '—')}</td>
                <td>${session.entries_count ?? 0}</td>
                <td>${statusPill(session.status)}</td>
                <td class="text-end">${renderActionGroup([
                    renderViewIconButton('data-view-calibration', session.id),
                ])}</td>
            </tr>
        `).join('');

        renderPagination('calibration', data.data.pagination, load);
    };

    const renderEntries = (session) => {
        const entriesBody = document.getElementById('calibrationEntriesBody');
        const finalizeBtn = document.getElementById('finalizeCalibrationBtn');
        activeSessionId = session.id;

        document.getElementById('calibrationDetailTitle').textContent = session.name;
        document.getElementById('calibrationDetailMeta').textContent = `${session.cycle?.name || 'Manual session'} • ${session.entries?.length || 0} employees`;
        finalizeBtn?.classList.toggle('d-none', session.status === 'finalized');

        if (!session.entries?.length) {
            entriesBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No entries in this session.</td></tr>';
            return;
        }

        entriesBody.innerHTML = session.entries.map((entry) => `
            <tr>
                <td>${escapeHtml(entry.employee?.full_name || '—')}</td>
                <td>${entry.original_rating ?? '—'}</td>
                <td>
                    <input type="number" class="form-control form-control-sm" min="0" max="5" step="0.1"
                        value="${entry.calibrated_rating ?? ''}" data-calibration-entry="${entry.id}" ${session.status === 'finalized' ? 'disabled' : ''}>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" value="${escapeHtml(entry.notes || '')}"
                        data-calibration-notes="${entry.id}" ${session.status === 'finalized' ? 'disabled' : ''}>
                </td>
                <td>${statusPill(entry.status)}</td>
            </tr>
        `).join('');
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openCalibrationModalBtn">+ Create Session</button>');

        document.getElementById('openCalibrationModalBtn')?.addEventListener('click', async () => {
            document.getElementById('calibrationForm')?.reset();
            await loadReviewCycles('calibrationCycleId');
            modal?.show();
        });

        document.getElementById('calibrationForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                await api.post('/performance-calibration', {
                    name: document.getElementById('calibrationName').value,
                    description: document.getElementById('calibrationDescription').value || null,
                    cycle_id: Number(document.getElementById('calibrationCycleId').value) || null,
                });
                modal?.hide();
                showAlert('Calibration session created.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('calibrationEntriesBody')?.addEventListener('change', async (e) => {
            const ratingInput = e.target.closest('[data-calibration-entry]');
            const notesInput = e.target.closest('[data-calibration-notes]');
            const entryId = ratingInput?.dataset.calibrationEntry || notesInput?.dataset.calibrationNotes;
            if (!entryId || !activeSessionId) return;

            try {
                await api.patch(`/performance-calibration/${activeSessionId}/entries/${entryId}`, {
                    calibrated_rating: document.querySelector(`[data-calibration-entry="${entryId}"]`)?.value || null,
                    notes: document.querySelector(`[data-calibration-notes="${entryId}"]`)?.value || null,
                });
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('finalizeCalibrationBtn')?.addEventListener('click', async () => {
            if (!activeSessionId || !window.confirm('Finalize this session and apply calibrated ratings to reviews?')) return;
            try {
                const { data } = await api.patch(`/performance-calibration/${activeSessionId}/finalize`);
                renderEntries(data.data.session);
                showAlert('Calibration session finalized.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const viewBtn = e.target.closest('[data-view-calibration]');
        if (!viewBtn) return;

        try {
            const { data } = await api.get(`/performance-calibration/${viewBtn.dataset.viewCalibration}`);
            renderEntries(data.data.session);
            detailModal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    ['calibrationStatusFilter', 'calibrationSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initPromotions = async () => {
    const body = document.getElementById('promotionsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('promotionModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;
    const promotionEmployeeSearch = bindEmployeeSearchSelect({
        inputId: 'promotionEmployeeSearch',
        hiddenId: 'promotionEmployeeId',
    });

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = {
            page: pageNum,
            per_page: readPerPage(document.getElementById('promotionsPerPage')),
            status: document.getElementById('promotionStatusFilter')?.value || undefined,
            search: document.getElementById('promotionSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/promotions', { params });
        const nominations = data.data.nominations || [];

        if (!nominations.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No promotion nominations found.</td></tr>';
            renderPagination('promotions', data.data.pagination, load);
            return;
        }

        body.innerHTML = nominations.map((item) => {
            const actions = [];
            if (item.status === 'draft') {
                actions.push(renderEditIconButton('data-edit-promotion', item.id));
                actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" title="Nominate" data-nominate-promotion="${item.id}">&#9654;</button>`);
            }
            if (cfg.canManage && item.status === 'nominated') {
                actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" title="Approve" data-approve-promotion="${item.id}">&#10003;</button>`);
                actions.push(`<button type="button" class="table-action-btn table-action-btn--reject" title="Reject" data-reject-promotion="${item.id}">&#10007;</button>`);
            }

            return `
            <tr>
                <td>${escapeHtml(item.employee?.full_name || '—')}</td>
                <td>${escapeHtml(item.current_designation || item.employee?.designation || '—')}</td>
                <td>${escapeHtml(item.proposed_designation)}</td>
                <td>${escapeHtml(item.effective_date || '—')}</td>
                <td>${statusPill(item.status)}</td>
                <td class="text-end">${actions.length ? renderActionGroup(actions) : '—'}</td>
            </tr>
        `;
        }).join('');

        renderPagination('promotions', data.data.pagination, load);
    };

    if (cfg.canManage || cfg.canReview) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openPromotionModalBtn">+ Nominate Promotion</button>');

        document.getElementById('openPromotionModalBtn')?.addEventListener('click', () => {
            document.getElementById('promotionEditingId').value = '';
            document.getElementById('promotionModalLabel').textContent = 'Create Promotion Nomination';
            document.getElementById('promotionForm').reset();
            promotionEmployeeSearch?.clearSelection();
            modal?.show();
        });

        document.getElementById('promotionForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('promotionEditingId').value;
            const payload = {
                employee_id: Number(document.getElementById('promotionEmployeeId').value),
                current_designation: document.getElementById('promotionCurrentDesignation').value || null,
                proposed_designation: document.getElementById('promotionProposedDesignation').value,
                justification: document.getElementById('promotionJustification').value || null,
                effective_date: document.getElementById('promotionEffectiveDate').value || null,
            };

            try {
                if (id) await api.put(`/promotions/${id}`, payload);
                else await api.post('/promotions', payload);
                modal?.hide();
                showAlert('Promotion nomination saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-promotion]');
        const nominateBtn = e.target.closest('[data-nominate-promotion]');
        const approveBtn = e.target.closest('[data-approve-promotion]');
        const rejectBtn = e.target.closest('[data-reject-promotion]');

        try {
            if (editBtn) {
                const { data } = await api.get(`/promotions/${editBtn.dataset.editPromotion}`);
                const item = data.data.nomination;
                document.getElementById('promotionEditingId').value = item.id;
                document.getElementById('promotionModalLabel').textContent = 'Edit Promotion Nomination';
                document.getElementById('promotionProposedDesignation').value = item.proposed_designation;
                document.getElementById('promotionCurrentDesignation').value = item.current_designation || '';
                document.getElementById('promotionJustification').value = item.justification || '';
                document.getElementById('promotionEffectiveDate').value = item.effective_date || '';
                promotionEmployeeSearch?.setSelection(item.employee?.id ? {
                    id: item.employee.id,
                    label: formatEmployeeLabel(item.employee),
                } : null);
                modal?.show();
                return;
            }

            if (nominateBtn) {
                await api.patch(`/promotions/${nominateBtn.dataset.nominatePromotion}/status`, { status: 'nominated' });
                showAlert('Promotion nominated.');
                await load(currentPage);
                return;
            }

            if (approveBtn) {
                await api.patch(`/promotions/${approveBtn.dataset.approvePromotion}/status`, { status: 'approved' });
                showAlert('Promotion approved.');
                await load(currentPage);
                return;
            }

            if (rejectBtn) {
                await api.patch(`/promotions/${rejectBtn.dataset.rejectPromotion}/status`, { status: 'rejected' });
                showAlert('Promotion rejected.');
                await load(currentPage);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    ['promotionStatusFilter', 'promotionSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initCompensation = async () => {
    if (!cfg.canManage) {
        setHeaderAction('');
        document.getElementById('bandsTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">You do not have permission to manage compensation plans.</td></tr>';
        return;
    }

    const bandsBody = document.getElementById('bandsTableBody');
    const meritBody = document.getElementById('meritTableBody');
    const bandModal = document.getElementById('bandModal') ? Modal.getOrCreateInstance(document.getElementById('bandModal')) : null;
    const meritModal = document.getElementById('meritModal') ? Modal.getOrCreateInstance(document.getElementById('meritModal')) : null;
    let bandsPage = 1;
    let meritPage = 1;

    const meritEmployeeSearch = bindEmployeeSearchSelect({
        inputId: 'meritEmployeeSearch',
        hiddenId: 'meritEmployeeId',
    });

    const loadBands = async (pageNum = 1) => {
        bandsPage = pageNum;
        const { data } = await api.get('/compensation-bands', {
            params: {
                page: pageNum,
                per_page: readPerPage(document.getElementById('bandsPerPage')),
                search: document.getElementById('bandSearchFilter')?.value || undefined,
            },
        });
        const bands = data.data.bands || [];
        bandsBody.innerHTML = bands.length ? bands.map((band) => `
            <tr>
                <td>${escapeHtml(band.name)}</td>
                <td>${escapeHtml(band.grade || '—')}</td>
                <td>${formatMoney(band.min_salary, band.currency)}</td>
                <td>${band.mid_salary ? formatMoney(band.mid_salary, band.currency) : '—'}</td>
                <td>${formatMoney(band.max_salary, band.currency)}</td>
                <td>${band.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
                <td class="text-end">${renderActionGroup([renderEditIconButton('data-edit-band', band.id)])}</td>
            </tr>
        `).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No salary bands found.</td></tr>';
        renderPagination('bands', data.data.pagination, loadBands);
    };

    const loadMerit = async (pageNum = 1) => {
        meritPage = pageNum;
        const { data } = await api.get('/compensation-recommendations', {
            params: {
                page: pageNum,
                per_page: readPerPage(document.getElementById('meritPerPage')),
                status: document.getElementById('meritStatusFilter')?.value || undefined,
                search: document.getElementById('meritSearchFilter')?.value || undefined,
            },
        });
        const items = data.data.recommendations || [];
        meritBody.innerHTML = items.length ? items.map((item) => `
            <tr>
                <td>${escapeHtml(item.employee?.full_name || '—')}</td>
                <td>${formatMoney(item.current_salary)}</td>
                <td>${item.recommended_increase_percent ?? '—'}%</td>
                <td>${formatMoney(item.recommended_new_salary)}</td>
                <td>${escapeHtml(item.band?.name || '—')}</td>
                <td>${statusPill(item.status)}</td>
                <td class="text-end">${renderActionGroup([
                    renderEditIconButton('data-edit-merit', item.id),
                    item.status === 'draft' ? `<button type="button" class="table-action-btn table-action-btn--approve" title="Propose" data-propose-merit="${item.id}">&#9654;</button>` : '',
                    item.status === 'proposed' ? `<button type="button" class="table-action-btn table-action-btn--approve" title="Approve" data-approve-merit="${item.id}">&#10003;</button>` : '',
                ].filter(Boolean))}</td>
            </tr>
        `).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No merit recommendations found.</td></tr>';
        renderPagination('merit', data.data.pagination, loadMerit);
    };

    const loadBandOptions = async () => {
        const { data } = await api.get('/compensation-bands', { params: { per_page: 100, active_only: 1 } });
        const select = document.getElementById('meritBandId');
        if (!select) return;
        select.innerHTML = '<option value="">Select band</option>' + (data.data.bands || []).map((band) => (
            `<option value="${band.id}">${escapeHtml(band.name)}</option>`
        )).join('');
    };

    setHeaderAction(`
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" id="openBandModalBtn">+ Salary Band</button>
            <button type="button" class="btn btn-primary" id="openMeritModalBtn">+ Merit Recommendation</button>
        </div>
    `);

    document.getElementById('openBandModalBtn')?.addEventListener('click', () => {
        document.getElementById('bandEditingId').value = '';
        document.getElementById('bandModalLabel').textContent = 'Create Salary Band';
        document.getElementById('bandForm').reset();
        bandModal?.show();
    });

    document.getElementById('openMeritModalBtn')?.addEventListener('click', async () => {
        document.getElementById('meritEditingId').value = '';
        document.getElementById('meritModalLabel').textContent = 'Create Merit Recommendation';
        document.getElementById('meritForm').reset();
        meritEmployeeSearch?.clearSelection();
        await loadBandOptions();
        meritModal?.show();
    });

    document.getElementById('bandForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('bandEditingId').value;
        const payload = {
            name: document.getElementById('bandName').value,
            grade: document.getElementById('bandGrade').value || null,
            min_salary: Number(document.getElementById('bandMin').value),
            mid_salary: document.getElementById('bandMid').value ? Number(document.getElementById('bandMid').value) : null,
            max_salary: Number(document.getElementById('bandMax').value),
            description: document.getElementById('bandDescription').value || null,
        };

        try {
            if (id) await api.put(`/compensation-bands/${id}`, payload);
            else await api.post('/compensation-bands', payload);
            bandModal?.hide();
            showAlert('Salary band saved.');
            await loadBands(bandsPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('meritForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('meritEditingId').value;
        const payload = {
            employee_id: Number(document.getElementById('meritEmployeeId').value),
            band_id: Number(document.getElementById('meritBandId').value) || null,
            current_salary: document.getElementById('meritCurrentSalary').value ? Number(document.getElementById('meritCurrentSalary').value) : null,
            recommended_increase_percent: document.getElementById('meritIncreasePercent').value ? Number(document.getElementById('meritIncreasePercent').value) : null,
            recommended_new_salary: document.getElementById('meritNewSalary').value ? Number(document.getElementById('meritNewSalary').value) : null,
            notes: document.getElementById('meritNotes').value || null,
        };

        try {
            if (id) await api.put(`/compensation-recommendations/${id}`, payload);
            else await api.post('/compensation-recommendations', payload);
            meritModal?.hide();
            showAlert('Merit recommendation saved.');
            await loadMerit(meritPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    bandsBody?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-band]');
        if (!editBtn) return;
        try {
            const { data } = await api.get('/compensation-bands', { params: { per_page: 100 } });
            const band = (data.data.bands || []).find((item) => String(item.id) === String(editBtn.dataset.editBand));
            if (!band) return;
            document.getElementById('bandEditingId').value = band.id;
            document.getElementById('bandModalLabel').textContent = 'Edit Salary Band';
            document.getElementById('bandName').value = band.name;
            document.getElementById('bandGrade').value = band.grade || '';
            document.getElementById('bandMin').value = band.min_salary;
            document.getElementById('bandMid').value = band.mid_salary ?? '';
            document.getElementById('bandMax').value = band.max_salary;
            document.getElementById('bandDescription').value = band.description || '';
            bandModal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    meritBody?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-merit]');
        const proposeBtn = e.target.closest('[data-propose-merit]');
        const approveBtn = e.target.closest('[data-approve-merit]');

        try {
            if (editBtn) {
                const { data } = await api.get('/compensation-recommendations', { params: { per_page: 100 } });
                const item = (data.data.recommendations || []).find((row) => String(row.id) === String(editBtn.dataset.editMerit));
                if (!item) return;
                await loadBandOptions();
                document.getElementById('meritEditingId').value = item.id;
                document.getElementById('meritModalLabel').textContent = 'Edit Merit Recommendation';
                meritEmployeeSearch?.setSelection(item.employee?.id ? { id: item.employee.id, label: formatEmployeeLabel(item.employee) } : null);
                document.getElementById('meritBandId').value = item.band?.id || '';
                document.getElementById('meritCurrentSalary').value = item.current_salary ?? '';
                document.getElementById('meritIncreasePercent').value = item.recommended_increase_percent ?? '';
                document.getElementById('meritNewSalary').value = item.recommended_new_salary ?? '';
                document.getElementById('meritNotes').value = item.notes || '';
                meritModal?.show();
                return;
            }

            if (proposeBtn) {
                await api.patch(`/compensation-recommendations/${proposeBtn.dataset.proposeMerit}/status`, { status: 'proposed' });
                showAlert('Merit recommendation proposed.');
                await loadMerit(meritPage);
                return;
            }

            if (approveBtn) {
                await api.patch(`/compensation-recommendations/${approveBtn.dataset.approveMerit}/status`, { status: 'approved' });
                showAlert('Merit recommendation approved.');
                await loadMerit(meritPage);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('meritIncreasePercent')?.addEventListener('input', () => {
        const current = Number(document.getElementById('meritCurrentSalary').value || 0);
        const percent = Number(document.getElementById('meritIncreasePercent').value || 0);
        if (current > 0 && percent >= 0) {
            document.getElementById('meritNewSalary').value = Math.round(current + (current * percent / 100));
        }
    });

    ['bandSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => loadBands(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });
    ['meritStatusFilter', 'meritSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => loadMerit(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
        document.getElementById(id)?.addEventListener('change', () => loadMerit(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await Promise.all([loadBands(), loadMerit()]);
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initSkills = async () => {
    const profilesBody = document.getElementById('skillProfilesTableBody');
    if (!profilesBody) return;

    const profileModal = document.getElementById('skillProfileModal') ? Modal.getOrCreateInstance(document.getElementById('skillProfileModal')) : null;
    const competencyModal = document.getElementById('competencyModal') ? Modal.getOrCreateInstance(document.getElementById('competencyModal')) : null;
    let profilesPage = 1;
    let competenciesPage = 1;

    const profileEmployeeSearch = bindEmployeeSearchSelect({
        inputId: 'skillProfileEmployeeSearch',
        hiddenId: 'skillProfileEmployeeId',
    });

    const loadCompetencyOptions = async () => {
        const { data } = await api.get('/competencies', { params: { per_page: 100, active_only: 1 } });
        const select = document.getElementById('skillProfileCompetencyId');
        if (!select) return;
        select.innerHTML = '<option value="">Select competency</option>' + (data.data.competencies || []).map((item) => (
            `<option value="${item.id}">${escapeHtml(item.name)}</option>`
        )).join('');
    };

    const loadProfiles = async (pageNum = 1) => {
        profilesPage = pageNum;
        const { data } = await api.get('/employee-competencies', {
            params: {
                page: pageNum,
                per_page: readPerPage(document.getElementById('skillProfilesPerPage')),
                search: document.getElementById('skillProfileSearchFilter')?.value || undefined,
            },
        });
        const records = data.data.employee_competencies || [];
        profilesBody.innerHTML = records.length ? records.map((record) => `
            <tr>
                <td>${escapeHtml(record.employee?.full_name || '—')}</td>
                <td>${escapeHtml(record.competency?.name || '—')}</td>
                <td>${record.current_level}</td>
                <td>${record.target_level}</td>
                <td>${record.gap > 0 ? `<span class="badge bg-warning">${record.gap}</span>` : '<span class="badge bg-success">0</span>'}</td>
                <td class="text-end">${renderActionGroup([renderEditIconButton('data-edit-skill-profile', record.id)])}</td>
            </tr>
        `).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No skill profiles found.</td></tr>';
        renderPagination('skillProfiles', data.data.pagination, loadProfiles);
    };

    const loadCompetencies = async (pageNum = 1) => {
        const body = document.getElementById('competenciesTableBody');
        if (!body) return;

        competenciesPage = pageNum;
        const { data } = await api.get('/competencies', {
            params: {
                page: pageNum,
                per_page: readPerPage(document.getElementById('competenciesPerPage')),
                search: document.getElementById('competencySearchFilter')?.value || undefined,
            },
        });
        const items = data.data.competencies || [];
        body.innerHTML = items.length ? items.map((item) => `
            <tr>
                <td>${escapeHtml(item.name)}</td>
                <td>${escapeHtml(item.category || '—')}</td>
                <td>${item.max_level}</td>
                <td>${item.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
                <td class="text-end">${cfg.canManage ? renderActionGroup([renderEditIconButton('data-edit-competency', item.id)]) : '—'}</td>
            </tr>
        `).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">No competencies found.</td></tr>';
        renderPagination('competencies', data.data.pagination, loadCompetencies);
    };

    setHeaderAction(cfg.canManage
        ? '<div class="btn-group"><button type="button" class="btn btn-outline-primary" id="openCompetencyModalBtn">+ Competency</button><button type="button" class="btn btn-primary" id="openSkillProfileModalBtn">+ Skill Profile</button></div>'
        : '<button type="button" class="btn btn-primary" id="openSkillProfileModalBtn">+ Skill Profile</button>');

    document.getElementById('openSkillProfileModalBtn')?.addEventListener('click', async () => {
        document.getElementById('skillProfileEditingId').value = '';
        document.getElementById('skillProfileModalLabel').textContent = 'Assign Competency';
        document.getElementById('skillProfileForm').reset();
        profileEmployeeSearch?.clearSelection();
        await loadCompetencyOptions();
        profileModal?.show();
    });

    document.getElementById('openCompetencyModalBtn')?.addEventListener('click', () => {
        document.getElementById('competencyEditingId').value = '';
        document.getElementById('competencyModalLabel').textContent = 'Create Competency';
        document.getElementById('competencyForm').reset();
        document.getElementById('competencyMaxLevel').value = 5;
        competencyModal?.show();
    });

    document.getElementById('skillProfileForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('skillProfileEditingId').value;
        const payload = {
            employee_id: Number(document.getElementById('skillProfileEmployeeId').value),
            competency_id: Number(document.getElementById('skillProfileCompetencyId').value),
            current_level: Number(document.getElementById('skillProfileCurrentLevel').value || 1),
            target_level: Number(document.getElementById('skillProfileTargetLevel').value || 3),
            notes: document.getElementById('skillProfileNotes').value || null,
        };

        try {
            if (id) await api.put(`/employee-competencies/${id}`, payload);
            else await api.post('/employee-competencies', payload);
            profileModal?.hide();
            showAlert('Skill profile saved.');
            await loadProfiles(profilesPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('competencyForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('competencyEditingId').value;
        const payload = {
            name: document.getElementById('competencyName').value,
            category: document.getElementById('competencyCategory').value || null,
            description: document.getElementById('competencyDescription').value || null,
            max_level: Number(document.getElementById('competencyMaxLevel').value || 5),
        };

        try {
            if (id) await api.put(`/competencies/${id}`, payload);
            else await api.post('/competencies', payload);
            competencyModal?.hide();
            showAlert('Competency saved.');
            await loadCompetencies(competenciesPage);
            await loadCompetencyOptions();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    profilesBody.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-skill-profile]');
        if (!editBtn) return;

        try {
            const { data } = await api.get('/employee-competencies', { params: { per_page: 100 } });
            const record = (data.data.employee_competencies || []).find((row) => String(row.id) === String(editBtn.dataset.editSkillProfile));
            if (!record) return;
            await loadCompetencyOptions();
            document.getElementById('skillProfileEditingId').value = record.id;
            document.getElementById('skillProfileModalLabel').textContent = 'Edit Skill Profile';
            profileEmployeeSearch?.setSelection(record.employee?.id ? { id: record.employee.id, label: formatEmployeeLabel(record.employee) } : null);
            document.getElementById('skillProfileCompetencyId').value = record.competency?.id || '';
            document.getElementById('skillProfileCurrentLevel').value = record.current_level;
            document.getElementById('skillProfileTargetLevel').value = record.target_level;
            document.getElementById('skillProfileNotes').value = record.notes || '';
            profileModal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('competenciesTableBody')?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-competency]');
        if (!editBtn) return;

        try {
            const { data } = await api.get('/competencies', { params: { per_page: 100 } });
            const item = (data.data.competencies || []).find((row) => String(row.id) === String(editBtn.dataset.editCompetency));
            if (!item) return;
            document.getElementById('competencyEditingId').value = item.id;
            document.getElementById('competencyModalLabel').textContent = 'Edit Competency';
            document.getElementById('competencyName').value = item.name;
            document.getElementById('competencyCategory').value = item.category || '';
            document.getElementById('competencyDescription').value = item.description || '';
            document.getElementById('competencyMaxLevel').value = item.max_level;
            competencyModal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('library-tab')?.addEventListener('shown.bs.tab', () => {
        loadCompetencies(competenciesPage).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    ['skillProfileSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => loadProfiles(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });
    ['competencySearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', () => loadCompetencies(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await loadProfiles();
        if (cfg.canManage) await loadCompetencyOptions();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const inits = {
        overview: initOverview,
        reviews: initOverview,
        insights: initInsights,
        'review-cycles': initReviewCycles,
        'feedback-forms': initFeedbackForms,
        'continuous-feedback': initFeedbackForms,
        'question-bank': initQuestionBank,
        goals: initGoals,
        kpi: initKpi,
        pip: initPip,
        calibration: initCalibration,
        promotions: initPromotions,
        compensation: initCompensation,
        skills: initSkills,
    };

    inits[page]?.();
});
