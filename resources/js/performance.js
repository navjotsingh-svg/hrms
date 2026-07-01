import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect, formatEmployeeLabel } from './employee-autocomplete';
import {
    renderActionGroup,
    renderDeleteButton,
    renderEditIconButton,
} from './action-icons';

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

const renderPagination = (prefix, pagination, onPage) => {
    const info = document.getElementById(`${prefix}PaginationInfo`);
    const list = document.getElementById(`${prefix}PaginationList`);

    if (info && pagination) {
        info.textContent = pagination.total
            ? `Showing ${pagination.from}–${pagination.to} of ${pagination.total}`
            : 'No records';
    }

    if (!list || !pagination) return;

    list.innerHTML = '';
    for (let p = 1; p <= pagination.last_page; p += 1) {
        list.insertAdjacentHTML('beforeend', `
            <li class="page-item ${p === pagination.current_page ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${p}">${p}</button>
            </li>
        `);
    }

    list.querySelectorAll('[data-page]').forEach((btn) => {
        btn.addEventListener('click', () => onPage(Number(btn.dataset.page)));
    });
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

    const loadCycles = async () => {
        const status = document.getElementById('cycleStatusFilter')?.value || '';
        const { data } = await api.get('/performance-review-cycles');
        cycles = (data.data.cycles || []).filter((c) => !status || c.status === status);

        if (!cycles.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No review cycles found.</td></tr>';
            return;
        }

        body.innerHTML = cycles.map((cycle) => {
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
        }).join('');
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

    const load = async () => {
        const params = {
            category: document.getElementById('qbCategoryFilter')?.value || undefined,
            search: document.getElementById('qbSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/performance-question-bank', { params });
        const questions = data.data.questions || [];

        if (!questions.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No questions in bank.</td></tr>';
            return;
        }

        body.innerHTML = questions.map((q) => `
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
        `).join('');
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

    const load = async () => {
        const params = {
            status: document.getElementById('formStatusFilter')?.value || undefined,
            search: document.getElementById('formSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/performance-feedback-forms', { params });
        const forms = data.data.forms || [];

        if (!forms.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No feedback forms found.</td></tr>';
            return;
        }

        body.innerHTML = forms.map((form) => `
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
        `).join('');
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
            per_page: 10,
            status: document.getElementById('goalStatusFilter')?.value || undefined,
            search: document.getElementById('goalSearchFilter')?.value || undefined,
        };
        const { data } = await api.get('/goals', { params });
        const goals = data.data.goals || [];

        if (!goals.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No goals found.</td></tr>';
            renderPagination('goals', data.data.pagination, load);
            return;
        }

        body.innerHTML = goals.map((goal) => `
            <tr>
                <td>${escapeHtml(goal.title)}</td>
                <td>${escapeHtml(goal.employee?.full_name || '—')}</td>
                <td>${escapeHtml(goal.period_start || '—')} – ${escapeHtml(goal.period_end || '—')}</td>
                <td>${goal.progress ?? 0}%</td>
                <td>${statusPill(goal.status)}</td>
                <td class="text-end">${renderActionGroup([renderEditIconButton('data-edit-goal', goal.id)])}</td>
            </tr>
        `).join('');

        renderPagination('goals', data.data.pagination, load);
    };

    setHeaderAction('<button type="button" class="btn btn-primary" id="openGoalModalBtn">+ Create Goal</button>');

    document.getElementById('openGoalModalBtn')?.addEventListener('click', () => {
        document.getElementById('goalEditingId').value = '';
        document.getElementById('goalModalLabel').textContent = 'Create Goal';
        document.getElementById('goalForm').reset();
        renderKrRows(document.getElementById('keyResultsList'), [{ title: '', target_value: 100, current_value: 0 }]);
        modal?.show();
    });

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
        const payload = {
            title: document.getElementById('goalTitle').value,
            description: document.getElementById('goalDescription').value,
            period_start: document.getElementById('goalPeriodStart').value || null,
            period_end: document.getElementById('goalPeriodEnd').value || null,
            status: document.getElementById('goalStatus').value,
            visibility: document.getElementById('goalVisibility').value,
            key_results: collectKr(document.getElementById('keyResultsList')),
        };

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
        if (!editBtn) return;

        try {
            const { data } = await api.get(`/goals/${editBtn.dataset.editGoal}`);
            const goal = data.data.goal;
            document.getElementById('goalEditingId').value = goal.id;
            document.getElementById('goalModalLabel').textContent = 'Edit Goal';
            document.getElementById('goalTitle').value = goal.title;
            document.getElementById('goalDescription').value = goal.description || '';
            document.getElementById('goalPeriodStart').value = goal.period_start || '';
            document.getElementById('goalPeriodEnd').value = goal.period_end || '';
            document.getElementById('goalStatus').value = goal.status;
            document.getElementById('goalVisibility').value = goal.visibility;
            renderKrRows(document.getElementById('keyResultsList'), goal.key_results?.length ? goal.key_results : [{ title: '', target_value: 100, current_value: 0 }]);
            modal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    ['goalStatusFilter', 'goalSearchFilter'].forEach((id) => {
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
            per_page: 10,
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
            per_page: 10,
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
    };

    inits[page]?.();
});
