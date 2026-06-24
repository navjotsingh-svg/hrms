import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import {
    renderActionGroup,
    renderEditIconButton,
} from './action-icons';

const cfg = window.HRMS_HIRING || {};
const page = cfg.page || 'overview';

const STAGES = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const statusPill = (status) => {
    const map = {
        draft: 'secondary',
        pending: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'secondary',
        open: 'success',
        closed: 'dark',
        applied: 'primary',
        screening: 'info',
        interview: 'warning',
        offer: 'success',
        hired: 'success',
        scheduled: 'primary',
        completed: 'success',
        no_show: 'danger',
        sent: 'primary',
        accepted: 'success',
        declined: 'danger',
        withdrawn: 'secondary',
        low: 'secondary',
        normal: 'primary',
        high: 'warning',
        critical: 'danger',
    };

    return `<span class="badge bg-${map[status] || 'secondary'}">${escapeHtml(String(status || '').replace(/_/g, ' '))}</span>`;
};

const showAlert = (message, type = 'success') => {
    const alertBox = document.getElementById('hiringAlert');
    if (!alertBox) return;
    alertBox.className = `alert alert-${type} alert-dismissible fade show`;
    alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    alertBox.classList.remove('d-none');
};

const setHeaderAction = (html) => {
    const el = document.getElementById('hiringHeaderActions');
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

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

const toDatetimeLocal = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const loadDepartments = async (selectEl) => {
    if (!selectEl) return;
    try {
        const { data } = await api.get('/departments', { params: { per_page: 100, status: 'active' } });
        const departments = data.data.departments || [];
        selectEl.innerHTML = '<option value="">Select department</option>'
            + departments.map((d) => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
    } catch {
        selectEl.innerHTML = '<option value="">Select department</option>';
    }
};

const loadJobsSelect = async (selectEl) => {
    if (!selectEl) return [];
    try {
        const { data } = await api.get('/hiring-jobs', { params: { per_page: 50 } });
        const jobs = data.data.jobs || [];
        selectEl.innerHTML = '<option value="">Select job</option>'
            + jobs.map((j) => `<option value="${j.id}">${escapeHtml(j.title)}</option>`).join('');
        return jobs;
    } catch {
        selectEl.innerHTML = '<option value="">Select job</option>';
        return [];
    }
};

const loadCandidatesSelect = async (selectEl) => {
    if (!selectEl) return [];
    try {
        const { data } = await api.get('/hiring-candidates', { params: { per_page: 50 } });
        const candidates = data.data.candidates || [];
        selectEl.innerHTML = '<option value="">Select candidate</option>'
            + candidates.map((c) => `<option value="${c.id}">${escapeHtml(c.full_name)}</option>`).join('');
        return candidates;
    } catch {
        selectEl.innerHTML = '<option value="">Select candidate</option>';
        return [];
    }
};

const loadTemplatesSelect = async (selectEl) => {
    if (!selectEl) return [];
    try {
        const { data } = await api.get('/hiring-templates', { params: { per_page: 50 } });
        const templates = data.data.templates || [];
        selectEl.innerHTML = '<option value="">Select template</option>'
            + templates.map((t) => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
        return templates;
    } catch {
        selectEl.innerHTML = '<option value="">Select template</option>';
        return [];
    }
};

const renderStageSelect = (candidateId, currentStage) => {
    if (!cfg.canManage) return statusPill(currentStage);
    return `
        <select class="form-select form-select-sm" data-stage-select="${candidateId}" style="min-width: 130px;">
            ${STAGES.map((stage) => `<option value="${stage}" ${stage === currentStage ? 'selected' : ''}>${escapeHtml(stage.replace(/_/g, ' '))}</option>`).join('')}
        </select>
    `;
};

const initOverview = async () => {
    const body = document.getElementById('pipelineTableBody');
    if (!body) return;

    try {
        const { data } = await api.get('/hiring/overview');
        const overview = data.data.overview;

        document.getElementById('statOpenJobs').textContent = overview.open_jobs ?? '—';
        document.getElementById('statPendingRequisitions').textContent = overview.pending_requisitions ?? '—';
        document.getElementById('statActiveCandidates').textContent = overview.active_candidates ?? '—';
        document.getElementById('statUpcomingInterviews').textContent = overview.upcoming_interviews ?? '—';

        const pipeline = overview.pipeline || {};
        const rows = STAGES.filter((stage) => pipeline[stage]).map((stage) => `
            <tr>
                <td>${statusPill(stage)}</td>
                <td class="text-end fw-semibold">${pipeline[stage]}</td>
            </tr>
        `);

        body.innerHTML = rows.length
            ? rows.join('')
            : '<tr><td colspan="2" class="text-center text-muted py-4">No candidates in pipeline.</td></tr>';
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initRequisitions = async () => {
    const body = document.getElementById('requisitionsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('requisitionModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: 10 };
        const status = document.getElementById('requisitionStatusFilter')?.value;
        const search = document.getElementById('requisitionSearchFilter')?.value?.trim();
        if (status) params.status = status;
        if (search) params.search = search;

        const { data } = await api.get('/job-requisitions', { params });
        const requisitions = data.data.requisitions || [];

        if (!requisitions.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No requisitions found.</td></tr>';
        } else {
            body.innerHTML = requisitions.map((req) => {
                const actions = [];
                if (cfg.canCreateRequisition && req.status === 'draft') {
                    actions.push(renderEditIconButton('data-edit-requisition', req.id, 'Edit'));
                    actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" title="Submit" data-submit-requisition="${req.id}">&#9654;</button>`);
                }
                return `
                    <tr>
                        <td>${escapeHtml(req.title)}</td>
                        <td>${escapeHtml(req.department?.name || '—')}</td>
                        <td>${req.headcount ?? '—'}</td>
                        <td>${statusPill(req.urgency || 'normal')}</td>
                        <td>${statusPill(req.status)}</td>
                        <td class="text-end">${renderActionGroup(actions)}</td>
                    </tr>
                `;
            }).join('');
        }

        renderPagination('requisitions', data.data.pagination, load);
    };

    if (cfg.canCreateRequisition) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openRequisitionModalBtn">+ Create Requisition</button>');
        await loadDepartments(document.getElementById('requisitionDepartment'));

        document.getElementById('openRequisitionModalBtn')?.addEventListener('click', () => {
            document.getElementById('requisitionEditingId').value = '';
            document.getElementById('requisitionModalLabel').textContent = 'Create Requisition';
            document.getElementById('requisitionForm').reset();
            document.getElementById('requisitionUrgency').value = 'normal';
            document.getElementById('requisitionEmploymentType').value = 'full_time';
            document.getElementById('requisitionHeadcount').value = '1';
            modal?.show();
        });

        document.getElementById('requisitionForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('requisitionEditingId').value;
            const payload = {
                title: document.getElementById('requisitionTitle').value,
                department_id: document.getElementById('requisitionDepartment').value || null,
                headcount: Number(document.getElementById('requisitionHeadcount').value || 1),
                description: document.getElementById('requisitionDescription').value,
                urgency: document.getElementById('requisitionUrgency').value,
                employment_type: document.getElementById('requisitionEmploymentType').value,
            };

            try {
                if (id) {
                    await api.put(`/job-requisitions/${id}`, payload);
                } else {
                    await api.post('/job-requisitions', payload);
                }
                modal?.hide();
                showAlert('Requisition saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-requisition]');
        const submitBtn = e.target.closest('[data-submit-requisition]');

        try {
            if (editBtn) {
                const { data } = await api.get('/job-requisitions', { params: { per_page: 50 } });
                const req = (data.data.requisitions || []).find((r) => String(r.id) === editBtn.dataset.editRequisition);
                if (!req) return;
                document.getElementById('requisitionEditingId').value = req.id;
                document.getElementById('requisitionModalLabel').textContent = 'Edit Requisition';
                document.getElementById('requisitionTitle').value = req.title;
                document.getElementById('requisitionDepartment').value = req.department?.id || '';
                document.getElementById('requisitionHeadcount').value = req.headcount || 1;
                document.getElementById('requisitionDescription').value = req.description || '';
                document.getElementById('requisitionUrgency').value = req.urgency || 'normal';
                document.getElementById('requisitionEmploymentType').value = req.employment_type || 'full_time';
                modal?.show();
            }

            if (submitBtn) {
                await api.patch(`/job-requisitions/${submitBtn.dataset.submitRequisition}/submit`);
                showAlert('Requisition submitted for approval.');
                await load(currentPage);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    ['requisitionStatusFilter', 'requisitionSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initJobs = async () => {
    const body = document.getElementById('jobsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('jobModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: 10 };
        const status = document.getElementById('jobStatusFilter')?.value;
        const search = document.getElementById('jobSearchFilter')?.value?.trim();
        if (status) params.status = status;
        if (search) params.search = search;

        const { data } = await api.get('/hiring-jobs', { params });
        const jobs = data.data.jobs || [];

        if (!jobs.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No jobs found.</td></tr>';
        } else {
            body.innerHTML = jobs.map((job) => {
                const actions = [];
                if (cfg.canManage) {
                    actions.push(renderEditIconButton('data-edit-job', job.id, 'Edit'));
                    if (job.status === 'draft') {
                        actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" title="Publish" data-publish-job="${job.id}">&#9654;</button>`);
                    }
                    if (job.status === 'open') {
                        actions.push(`<button type="button" class="table-action-btn table-action-btn--reject" title="Close" data-close-job="${job.id}">&#9632;</button>`);
                    }
                }
                return `
                    <tr>
                        <td>${escapeHtml(job.title)}</td>
                        <td>${escapeHtml(job.location || '—')}</td>
                        <td>${escapeHtml((job.employment_type || '—').replace(/_/g, ' '))}</td>
                        <td>${escapeHtml(job.department?.name || '—')}</td>
                        <td>${statusPill(job.status)}</td>
                        <td class="text-end">${renderActionGroup(actions)}</td>
                    </tr>
                `;
            }).join('');
        }

        renderPagination('jobs', data.data.pagination, load);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openJobModalBtn">+ Create Job</button>');

        document.getElementById('openJobModalBtn')?.addEventListener('click', () => {
            document.getElementById('jobEditingId').value = '';
            document.getElementById('jobModalLabel').textContent = 'Create Job';
            document.getElementById('jobForm').reset();
            document.getElementById('jobEmploymentType').value = 'full_time';
            modal?.show();
        });

        document.getElementById('jobForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('jobEditingId').value;
            const payload = {
                title: document.getElementById('jobTitle').value,
                location: document.getElementById('jobLocation').value,
                employment_type: document.getElementById('jobEmploymentType').value,
                description_html: document.getElementById('jobDescriptionHtml').value,
            };

            try {
                if (id) {
                    await api.put(`/hiring-jobs/${id}`, payload);
                } else {
                    await api.post('/hiring-jobs', payload);
                }
                modal?.hide();
                showAlert('Job saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-job]');
        const publishBtn = e.target.closest('[data-publish-job]');
        const closeBtn = e.target.closest('[data-close-job]');

        try {
            if (editBtn) {
                const { data } = await api.get('/hiring-jobs', { params: { per_page: 50 } });
                const job = (data.data.jobs || []).find((j) => String(j.id) === editBtn.dataset.editJob);
                if (!job) return;
                document.getElementById('jobEditingId').value = job.id;
                document.getElementById('jobModalLabel').textContent = 'Edit Job';
                document.getElementById('jobTitle').value = job.title;
                document.getElementById('jobLocation').value = job.location || '';
                document.getElementById('jobEmploymentType').value = job.employment_type || 'full_time';
                document.getElementById('jobDescriptionHtml').value = job.description_html || '';
                modal?.show();
            }

            if (publishBtn) {
                await api.patch(`/hiring-jobs/${publishBtn.dataset.publishJob}/publish`);
                showAlert('Job published.');
                await load(currentPage);
            }

            if (closeBtn) {
                await api.patch(`/hiring-jobs/${closeBtn.dataset.closeJob}/close`);
                showAlert('Job closed.');
                await load(currentPage);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    ['jobStatusFilter', 'jobSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initCandidates = async () => {
    const body = document.getElementById('candidatesTableBody');
    if (!body) return;

    const modalEl = document.getElementById('candidateModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: 10 };
        const stage = document.getElementById('candidateStageFilter')?.value;
        const search = document.getElementById('candidateSearchFilter')?.value?.trim();
        if (stage) params.stage = stage;
        if (search) params.search = search;

        const { data } = await api.get('/hiring-candidates', { params });
        const candidates = data.data.candidates || [];

        if (!candidates.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No candidates found.</td></tr>';
        } else {
            body.innerHTML = candidates.map((candidate) => `
                <tr>
                    <td>${escapeHtml(candidate.full_name)}</td>
                    <td>${escapeHtml(candidate.email)}</td>
                    <td>${escapeHtml(candidate.job?.title || '—')}</td>
                    <td>${escapeHtml(candidate.source || '—')}</td>
                    <td>${renderStageSelect(candidate.id, candidate.stage)}</td>
                    <td class="text-end">—</td>
                </tr>
            `).join('');
        }

        renderPagination('candidates', data.data.pagination, load);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openCandidateModalBtn">+ Add Candidate</button>');
        await loadJobsSelect(document.getElementById('candidateJob'));

        document.getElementById('openCandidateModalBtn')?.addEventListener('click', () => {
            document.getElementById('candidateForm').reset();
            document.getElementById('candidateModalLabel').textContent = 'Add Candidate';
            modal?.show();
        });

        document.getElementById('candidateForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                first_name: document.getElementById('candidateFirstName').value,
                last_name: document.getElementById('candidateLastName').value,
                email: document.getElementById('candidateEmail').value,
                phone: document.getElementById('candidatePhone').value || null,
                job_id: document.getElementById('candidateJob').value || null,
                source: document.getElementById('candidateSource').value || null,
                notes: document.getElementById('candidateNotes').value || null,
            };

            try {
                await api.post('/hiring-candidates', payload);
                modal?.hide();
                showAlert('Candidate added.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        body.addEventListener('change', async (e) => {
            const select = e.target.closest('[data-stage-select]');
            if (!select) return;

            try {
                await api.patch(`/hiring-candidates/${select.dataset.stageSelect}/stage`, { stage: select.value });
                showAlert('Candidate stage updated.');
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
                await load(currentPage);
            }
        });
    }

    ['candidateStageFilter', 'candidateSearchFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
        document.getElementById(id)?.addEventListener('input', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initInterviews = async () => {
    const body = document.getElementById('interviewsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('interviewModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: 10 };
        const status = document.getElementById('interviewStatusFilter')?.value;
        if (status) params.status = status;

        const { data } = await api.get('/hiring-interviews', { params });
        const interviews = data.data.interviews || [];

        if (!interviews.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No interviews found.</td></tr>';
        } else {
            body.innerHTML = interviews.map((interview) => {
                const actions = [];
                if (cfg.canInterview) {
                    actions.push(renderEditIconButton('data-edit-interview', interview.id, 'Edit'));
                }
                return `
                    <tr>
                        <td>${escapeHtml(interview.title)}</td>
                        <td>${escapeHtml(interview.candidate?.full_name || '—')}</td>
                        <td>${formatDateTime(interview.scheduled_at)}</td>
                        <td>${escapeHtml(interview.location || '—')}</td>
                        <td>${statusPill(interview.status)}</td>
                        <td class="text-end">${renderActionGroup(actions)}</td>
                    </tr>
                `;
            }).join('');
        }

        renderPagination('interviews', data.data.pagination, load);
    };

    if (cfg.canInterview) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openInterviewModalBtn">+ Schedule Interview</button>');
        await loadCandidatesSelect(document.getElementById('interviewCandidate'));

        document.getElementById('openInterviewModalBtn')?.addEventListener('click', () => {
            document.getElementById('interviewEditingId').value = '';
            document.getElementById('interviewModalLabel').textContent = 'Schedule Interview';
            document.getElementById('interviewForm').reset();
            modal?.show();
        });

        document.getElementById('interviewForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('interviewEditingId').value;
            const payload = {
                candidate_id: Number(document.getElementById('interviewCandidate').value),
                title: document.getElementById('interviewTitle').value,
                scheduled_at: document.getElementById('interviewScheduledAt').value,
                location: document.getElementById('interviewLocation').value || null,
                meeting_link: document.getElementById('interviewMeetingLink').value || null,
            };

            try {
                if (id) {
                    await api.put(`/hiring-interviews/${id}`, payload);
                } else {
                    await api.post('/hiring-interviews', payload);
                }
                modal?.hide();
                showAlert('Interview saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-interview]');
        if (!editBtn) return;

        try {
            const { data } = await api.get('/hiring-interviews', { params: { per_page: 50 } });
            const interview = (data.data.interviews || []).find((i) => String(i.id) === editBtn.dataset.editInterview);
            if (!interview) return;
            document.getElementById('interviewEditingId').value = interview.id;
            document.getElementById('interviewModalLabel').textContent = 'Edit Interview';
            document.getElementById('interviewCandidate').value = interview.candidate?.id || '';
            document.getElementById('interviewTitle').value = interview.title;
            document.getElementById('interviewScheduledAt').value = toDatetimeLocal(interview.scheduled_at);
            document.getElementById('interviewLocation').value = interview.location || '';
            document.getElementById('interviewMeetingLink').value = interview.meeting_link || '';
            modal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('interviewStatusFilter')?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initOffers = async () => {
    const body = document.getElementById('offersTableBody');
    if (!body) return;

    const modalEl = document.getElementById('offerModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: 10 };
        const status = document.getElementById('offerStatusFilter')?.value;
        if (status) params.status = status;

        const { data } = await api.get('/hiring-offers', { params });
        const offers = data.data.offers || [];

        if (!offers.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No offers found.</td></tr>';
        } else {
            body.innerHTML = offers.map((offer) => {
                const actions = [];
                if (cfg.canManage && offer.status === 'draft') {
                    actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" title="Send" data-send-offer="${offer.id}">&#9993;</button>`);
                }
                return `
                    <tr>
                        <td>${escapeHtml(offer.title)}</td>
                        <td>${escapeHtml(offer.candidate?.full_name || '—')}</td>
                        <td>${escapeHtml(offer.job?.title || '—')}</td>
                        <td>${offer.offered_ctc ?? '—'}</td>
                        <td>${escapeHtml(offer.joining_date || '—')}</td>
                        <td>${statusPill(offer.status)}</td>
                        <td class="text-end">${renderActionGroup(actions)}</td>
                    </tr>
                `;
            }).join('');
        }

        renderPagination('offers', data.data.pagination, load);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openOfferModalBtn">+ Create Offer</button>');
        await Promise.all([
            loadCandidatesSelect(document.getElementById('offerCandidate')),
            loadJobsSelect(document.getElementById('offerJob')),
            loadTemplatesSelect(document.getElementById('offerTemplate')),
        ]);

        document.getElementById('openOfferModalBtn')?.addEventListener('click', () => {
            document.getElementById('offerForm').reset();
            document.getElementById('offerModalLabel').textContent = 'Create Offer';
            modal?.show();
        });

        document.getElementById('offerForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                candidate_id: Number(document.getElementById('offerCandidate').value),
                job_id: document.getElementById('offerJob').value || null,
                template_id: document.getElementById('offerTemplate').value || null,
                title: document.getElementById('offerTitle').value,
                offered_ctc: document.getElementById('offerCtc').value || null,
                joining_date: document.getElementById('offerJoiningDate').value || null,
                letter_html: document.getElementById('offerLetterHtml').value || null,
            };

            try {
                await api.post('/hiring-offers', payload);
                modal?.hide();
                showAlert('Offer created.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const sendBtn = e.target.closest('[data-send-offer]');
        if (!sendBtn) return;

        try {
            await api.patch(`/hiring-offers/${sendBtn.dataset.sendOffer}/send`);
            showAlert('Offer sent.');
            await load(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    document.getElementById('offerStatusFilter')?.addEventListener('change', () => load(1).catch((err) => showAlert(getErrorMessage(err), 'danger')));

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initTemplates = async () => {
    const body = document.getElementById('templatesTableBody');
    if (!body) return;

    const modalEl = document.getElementById('templateModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const { data } = await api.get('/hiring-templates', { params: { page: pageNum, per_page: 10 } });
        const templates = data.data.templates || [];

        if (!templates.length) {
            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No templates found.</td></tr>';
        } else {
            body.innerHTML = templates.map((template) => {
                const actions = [];
                if (cfg.canManage) {
                    actions.push(renderEditIconButton('data-edit-template', template.id, 'Edit'));
                }
                return `
                    <tr>
                        <td>${escapeHtml(template.name)}</td>
                        <td>${escapeHtml(template.type || '—')}</td>
                        <td>${template.is_default ? 'Yes' : 'No'}</td>
                        <td class="text-end">${renderActionGroup(actions)}</td>
                    </tr>
                `;
            }).join('');
        }

        renderPagination('templates', data.data.pagination, load);
    };

    if (cfg.canManage) {
        setHeaderAction('<button type="button" class="btn btn-primary" id="openTemplateModalBtn">+ Create Template</button>');

        document.getElementById('openTemplateModalBtn')?.addEventListener('click', () => {
            document.getElementById('templateEditingId').value = '';
            document.getElementById('templateModalLabel').textContent = 'Create Template';
            document.getElementById('templateForm').reset();
            document.getElementById('templateType').value = 'offer';
            modal?.show();
        });

        document.getElementById('templateForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('templateEditingId').value;
            const payload = {
                name: document.getElementById('templateName').value,
                type: document.getElementById('templateType').value,
                body_html: document.getElementById('templateBodyHtml').value,
            };

            try {
                if (id) {
                    await api.put(`/hiring-templates/${id}`, payload);
                } else {
                    await api.post('/hiring-templates', payload);
                }
                modal?.hide();
                showAlert('Template saved.');
                await load(currentPage);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    body.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit-template]');
        if (!editBtn) return;

        try {
            const { data } = await api.get('/hiring-templates', { params: { per_page: 50 } });
            const template = (data.data.templates || []).find((t) => String(t.id) === editBtn.dataset.editTemplate);
            if (!template) return;
            document.getElementById('templateEditingId').value = template.id;
            document.getElementById('templateModalLabel').textContent = 'Edit Template';
            document.getElementById('templateName').value = template.name;
            document.getElementById('templateType').value = template.type || 'offer';
            document.getElementById('templateBodyHtml').value = template.body_html || '';
            modal?.show();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    try {
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initCareers = async () => {
    const form = document.getElementById('careersForm');
    if (!form) return;

    const previewLink = document.getElementById('careersPreviewLink');
    const publicUrlInput = document.getElementById('careersPublicUrl');
    const bannerPreview = document.getElementById('careersBannerPreview');

    const populate = (settings) => {
        document.getElementById('careersHeroTitle').value = settings.hero_title || '';
        document.getElementById('careersHeroSubtitle').value = settings.hero_subtitle || '';
        document.getElementById('careersAboutHtml').value = settings.about_html || '';
        document.getElementById('careersHeaderHtml').value = settings.header_html || '';
        document.getElementById('careersFooterHtml').value = settings.footer_html || '';
        document.getElementById('careersIsPublished').checked = Boolean(settings.is_published);

        if (publicUrlInput) publicUrlInput.value = settings.public_url || '';
        if (previewLink) {
            previewLink.href = settings.public_url || '#';
            previewLink.classList.toggle('disabled', !settings.public_url);
        }

        if (bannerPreview) {
            bannerPreview.innerHTML = settings.banner_url
                ? `<img src="${escapeHtml(settings.banner_url)}" alt="Banner preview" class="img-fluid rounded border" style="max-height: 160px;">`
                : '';
        }
    };

    try {
        const { data } = await api.get('/hiring/careers-page');
        populate(data.data.settings || {});
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData();
        formData.append('hero_title', document.getElementById('careersHeroTitle').value);
        formData.append('hero_subtitle', document.getElementById('careersHeroSubtitle').value);
        formData.append('about_html', document.getElementById('careersAboutHtml').value);
        formData.append('header_html', document.getElementById('careersHeaderHtml').value);
        formData.append('footer_html', document.getElementById('careersFooterHtml').value);
        formData.append('is_published', document.getElementById('careersIsPublished').checked ? '1' : '0');

        const bannerFile = document.getElementById('careersBanner')?.files?.[0];
        if (bannerFile) formData.append('banner', bannerFile);

        try {
            const { data } = await api.post('/hiring/careers-page', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            populate(data.data.settings || {});
            document.getElementById('careersBanner').value = '';
            showAlert(data.message || 'Careers page updated.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const inits = {
        overview: initOverview,
        requisitions: initRequisitions,
        jobs: initJobs,
        candidates: initCandidates,
        interviews: initInterviews,
        offers: initOffers,
        templates: initTemplates,
        careers: initCareers,
    };

    inits[page]?.();
});
