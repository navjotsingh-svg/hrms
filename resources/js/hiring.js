import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { aiGenerateJobDescription } from './ai-tools';
import {
    renderActionGroup,
    renderEditIconButton,
    renderViewIconButton,
} from './action-icons';
import {
    bindPagination,
    bindPerPageSelect,
    readPerPage,
    renderListPagination,
} from './pagination';
import { renderDateTimeStack } from './datetime-utils';
import { initRichTextEditor, isEmptyEditorContent } from './rich-text-editor';

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

const openOfferPdf = async (offerId, filename = 'offer-letter.pdf') => {
    const response = await api.get(`/hiring-offers/${offerId}/pdf`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
    const popup = window.open(url, '_blank');

    if (!popup) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    setTimeout(() => URL.revokeObjectURL(url), 60_000);
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

const formatDateTime = (value) => renderDateTimeStack(value);

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

const detailField = (label, value) => `
    <div class="col-sm-6">
        <div class="small text-muted">${escapeHtml(label)}</div>
        <div>${value || '—'}</div>
    </div>
`;

const renderCandidateDetail = (candidate) => {
    const stageLogs = (candidate.stage_logs || []).length
        ? `<div class="list-group list-group-flush">
            ${candidate.stage_logs.map((log) => `
                <div class="list-group-item px-0">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <strong>${escapeHtml((log.from_stage || 'new').replace(/_/g, ' '))}</strong>
                            <span class="text-muted"> → </span>
                            <strong>${escapeHtml((log.to_stage || '').replace(/_/g, ' '))}</strong>
                        </div>
                        <div class="small text-muted">${formatDateTime(log.created_at)}</div>
                    </div>
                    ${log.actor_name ? `<div class="small text-muted">By ${escapeHtml(log.actor_name)}</div>` : ''}
                    ${log.notes ? `<div class="small mt-1">${escapeHtml(log.notes)}</div>` : ''}
                </div>
            `).join('')}
        </div>`
        : '<p class="text-muted mb-0">No stage history yet.</p>';

    const interviews = (candidate.interviews || []).length
        ? `<div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Interview</th>
                        <th>Scheduled</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${candidate.interviews.map((interview) => `
                        <tr>
                            <td>
                                <div>${escapeHtml(interview.title)}</div>
                                <div class="small text-muted">${escapeHtml(interview.job?.title || '—')}</div>
                                ${interview.location ? `<div class="small text-muted">${escapeHtml(interview.location)}</div>` : ''}
                                ${interview.meeting_link ? `<div class="small"><a href="${escapeHtml(interview.meeting_link)}" target="_blank" rel="noopener">Meeting link</a></div>` : ''}
                            </td>
                            <td>${formatDateTime(interview.scheduled_at)}</td>
                            <td>${statusPill(interview.status)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>`
        : '<p class="text-muted mb-0">No interviews scheduled.</p>';

    const offers = (candidate.offers || []).length
        ? `<div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Offer</th>
                        <th>CTC</th>
                        <th>Joining</th>
                        <th>Status</th>
                        ${cfg.canManage ? '<th class="text-end">Letter</th>' : ''}
                    </tr>
                </thead>
                <tbody>
                    ${candidate.offers.map((offer) => {
                        const signedMeta = offer.status === 'accepted' && offer.signed_at
                            ? `<div class="small text-muted">Signed ${formatDateTime(offer.signed_at)}${offer.signature_name ? ` by ${escapeHtml(offer.signature_name)}` : ''}</div>`
                            : '';
                        const declineMeta = offer.status === 'declined' && offer.decline_reason
                            ? `<div class="small text-muted">${escapeHtml(offer.decline_reason)}</div>`
                            : '';
                        const viewAction = cfg.canManage && offer.can_view_pdf
                            ? renderViewIconButton('data-view-offer-pdf', offer.id, offer.has_signed_pdf ? 'View signed offer letter' : 'View offer letter')
                            : '';

                        return `
                        <tr>
                            <td>
                                <div>${escapeHtml(offer.title)}</div>
                                <div class="small text-muted">${escapeHtml(offer.job?.title || '—')}</div>
                                ${signedMeta}
                                ${declineMeta}
                            </td>
                            <td>${offer.offered_ctc ?? '—'}</td>
                            <td>${escapeHtml(offer.joining_date || '—')}</td>
                            <td>${statusPill(offer.status)}</td>
                            ${cfg.canManage ? `<td class="text-end">${renderActionGroup([viewAction])}</td>` : ''}
                        </tr>
                    `;
                    }).join('')}
                </tbody>
            </table>
        </div>`
        : '<p class="text-muted mb-0">No offers created.</p>';

    return `
        <div class="mb-4">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">${escapeHtml(candidate.full_name)}</h2>
                ${statusPill(candidate.stage)}
            </div>
            <div class="row g-3">
                ${detailField('Email', escapeHtml(candidate.email))}
                ${detailField('Phone', escapeHtml(candidate.phone || '—'))}
                ${detailField('Job', escapeHtml(candidate.job?.title || '—'))}
                ${detailField('Source', escapeHtml(candidate.source || '—'))}
                ${detailField('Applied', formatDateTime(candidate.applied_at))}
                ${detailField('Recruiter', escapeHtml(candidate.assigned_recruiter?.name || '—'))}
                ${candidate.hired_at ? detailField('Hired', formatDateTime(candidate.hired_at)) : ''}
                ${candidate.rejected_at ? detailField('Rejected', formatDateTime(candidate.rejected_at)) : ''}
                ${candidate.employee ? detailField('Employee record', escapeHtml(`${candidate.employee.full_name}${candidate.employee.employee_code ? ` (${candidate.employee.employee_code})` : ''}`)) : ''}
            </div>
            ${candidate.rejection_reason ? `
                <div class="mt-3">
                    <div class="small text-muted">Rejection reason</div>
                    <div>${escapeHtml(candidate.rejection_reason)}</div>
                </div>
            ` : ''}
            ${candidate.notes ? `
                <div class="mt-3">
                    <div class="small text-muted">Notes</div>
                    <div class="text-pre-wrap">${escapeHtml(candidate.notes)}</div>
                </div>
            ` : ''}
            ${candidate.resume_url ? `
                <div class="mt-3">
                    <a href="${escapeHtml(candidate.resume_url)}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">View resume</a>
                </div>
            ` : ''}
        </div>

        <h3 class="h6 mb-2">Stage history</h3>
        <div class="mb-4">${stageLogs}</div>

        <h3 class="h6 mb-2">Interviews</h3>
        <div class="mb-4">${interviews}</div>

        <h3 class="h6 mb-2">Offers</h3>
        <div>${offers}</div>
    `;
};

const initOverview = async () => {
    const body = document.getElementById('pipelineTableBody');
    if (!body) return;

    try {
        const { data } = await api.get('/hiring/overview');
        const overview = data.data.overview;

        document.getElementById('statOpenJobs').textContent = overview.open_jobs ?? '—';
        document.getElementById('statDraftJobs').textContent = overview.draft_jobs ?? '—';
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

const initJobs = async () => {
    const body = document.getElementById('jobsTableBody');
    if (!body) return;

    const modalEl = document.getElementById('jobModal');
    const modal = modalEl ? Modal.getOrCreateInstance(modalEl) : null;
    let currentPage = 1;

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: readPerPage(document.getElementById('jobsPerPage')) };
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

        document.getElementById('jobAiGenerateBtn')?.addEventListener('click', async () => {
            const title = document.getElementById('jobTitle')?.value?.trim();
            const button = document.getElementById('jobAiGenerateBtn');

            if (!title) {
                showAlert('Enter a job title first.', 'warning');
                return;
            }

            button.disabled = true;
            button.textContent = 'AI working…';

            try {
                const result = await aiGenerateJobDescription({
                    title,
                    department: document.getElementById('jobLocation')?.value?.trim() || null,
                    requirements: document.getElementById('jobDescriptionHtml')?.value?.trim() || null,
                });
                document.getElementById('jobDescriptionHtml').value = result.body_html || '';
                showAlert('Job description generated. Review before saving.');
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                button.disabled = false;
                button.textContent = 'AI generate';
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
    const detailModalEl = document.getElementById('candidateDetailModal');
    const detailModal = detailModalEl ? Modal.getOrCreateInstance(detailModalEl) : null;
    const detailBody = document.getElementById('candidateDetailBody');
    const detailTitle = document.getElementById('candidateDetailModalLabel');
    let currentPage = 1;

    const openCandidateDetail = async (candidateId) => {
        if (!detailBody) return;

        detailTitle.textContent = 'Candidate Details';
        detailBody.innerHTML = '<div class="text-muted py-4 text-center">Loading…</div>';
        detailModal?.show();

        try {
            const { data } = await api.get(`/hiring-candidates/${candidateId}`);
            const candidate = data.data?.candidate;

            if (!candidate) {
                detailBody.innerHTML = '<div class="text-danger py-4 text-center">Candidate not found.</div>';
                return;
            }

            detailTitle.textContent = candidate.full_name || 'Candidate Details';
            detailBody.innerHTML = renderCandidateDetail(candidate);
        } catch (error) {
            detailBody.innerHTML = `<div class="text-danger py-4 text-center">${escapeHtml(getErrorMessage(error))}</div>`;
        }
    };

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const params = { page: pageNum, per_page: readPerPage(document.getElementById('candidatesPerPage')) };
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
                    <td class="text-end">${renderActionGroup(renderViewIconButton('data-view-candidate', candidate.id, `View ${candidate.full_name}`))}</td>
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

    body.addEventListener('click', async (e) => {
        const viewBtn = e.target.closest('[data-view-candidate]');
        if (!viewBtn) return;

        await openCandidateDetail(viewBtn.dataset.viewCandidate);
    });

    detailBody?.addEventListener('click', async (e) => {
        const viewPdfBtn = e.target.closest('[data-view-offer-pdf]');
        if (!viewPdfBtn) return;

        try {
            await openOfferPdf(viewPdfBtn.dataset.viewOfferPdf);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

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
        const params = { page: pageNum, per_page: readPerPage(document.getElementById('interviewsPerPage')) };
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
        const params = { page: pageNum, per_page: readPerPage(document.getElementById('offersPerPage')) };
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
                if (cfg.canManage && offer.can_view_pdf) {
                    actions.push(renderViewIconButton(
                        'data-view-offer-pdf',
                        offer.id,
                        offer.has_signed_pdf ? 'View signed offer letter' : 'View offer letter',
                    ));
                }
                const signedMeta = offer.status === 'accepted' && offer.signed_at
                    ? `<div class="small text-muted">Signed ${formatDateTime(offer.signed_at)}${offer.signature_name ? ` · ${escapeHtml(offer.signature_name)}` : ''}</div>`
                    : '';

                return `
                    <tr>
                        <td>
                            <div>${escapeHtml(offer.title)}</div>
                            ${signedMeta}
                        </td>
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
                template_id: Number(document.getElementById('offerTemplate').value),
                title: document.getElementById('offerTitle').value,
                offered_ctc: document.getElementById('offerCtc').value || null,
                joining_date: document.getElementById('offerJoiningDate').value || null,
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
        const viewPdfBtn = e.target.closest('[data-view-offer-pdf]');

        if (viewPdfBtn) {
            try {
                await openOfferPdf(viewPdfBtn.dataset.viewOfferPdf);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
            return;
        }

        if (!sendBtn) return;

        try {
            await api.patch(`/hiring-offers/${sendBtn.dataset.sendOffer}/send`);
            showAlert('Offer email with PDF sent to candidate.');
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
    let templateBodyEditor = null;
    let templateMeta = { placeholders: [], sample_templates: {} };

    const ensureTemplateBodyEditor = () => {
        if (templateBodyEditor) {
            return templateBodyEditor;
        }

        templateBodyEditor = initRichTextEditor({
            container: document.getElementById('templateBodyEditor'),
            textarea: document.getElementById('templateBodyHtml'),
            placeholder: 'Write the template content. Click dynamic fields below to insert placeholders.',
        });

        return templateBodyEditor;
    };

    const setTemplateBodyContent = (html = '') => {
        const editor = ensureTemplateBodyEditor();
        if (html && !isEmptyEditorContent(html)) {
            editor.quill.root.innerHTML = html;
        } else {
            editor.quill.setText('');
        }
        editor.sync?.();
    };

    const insertTemplatePlaceholder = (key) => {
        const editor = ensureTemplateBodyEditor();
        if (!editor?.quill) return;

        const token = `{${key}}`;
        const range = editor.quill.getSelection(true);
        editor.quill.insertText(range.index, token);
        editor.sync?.();
    };

    const renderPlaceholderGroups = () => {
        const container = document.getElementById('templatePlaceholderGroups');
        if (!container) return;

        const groups = {};
        (templateMeta.placeholders || []).forEach((item) => {
            const group = item.group || 'Fields';
            if (!groups[group]) {
                groups[group] = [];
            }
            groups[group].push(item);
        });

        container.innerHTML = Object.entries(groups).map(([group, items]) => `
            <div class="mb-2">
                <div class="small text-muted mb-1">${escapeHtml(group)}</div>
                <div class="d-flex flex-wrap gap-1">
                    ${items.map((item) => `
                        <button
                            type="button"
                            class="btn btn-outline-secondary btn-sm doc-letter-placeholder-btn hiring-template-field-btn"
                            data-placeholder-key="${escapeHtml(item.key)}"
                            title="${escapeHtml(item.label)}"
                        >{${escapeHtml(item.key)}}</button>
                    `).join('')}
                </div>
            </div>
        `).join('');
    };

    const resetTemplateForm = () => {
        document.getElementById('templateEditingId').value = '';
        document.getElementById('templateModalLabel').textContent = 'Create Template';
        document.getElementById('templateForm').reset();
        document.getElementById('templateType').value = 'offer';
        setTemplateBodyContent('');
    };

    const loadMeta = async () => {
        if (!cfg.canManage) return;

        try {
            const { data } = await api.get('/hiring-templates/meta');
            templateMeta = data.data || templateMeta;
            renderPlaceholderGroups();
        } catch {
            renderPlaceholderGroups();
        }
    };

    const load = async (pageNum = 1) => {
        currentPage = pageNum;
        const { data } = await api.get('/hiring-templates', {
            params: { page: pageNum, per_page: readPerPage(document.getElementById('templatesPerPage')) },
        });
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
            resetTemplateForm();
            modal?.show();
        });

        document.getElementById('useOfferSampleBtn')?.addEventListener('click', () => {
            setTemplateBodyContent(templateMeta.sample_templates?.offer || '');
        });

        document.getElementById('templatePlaceholderGroups')?.addEventListener('click', (event) => {
            const button = event.target.closest('.hiring-template-field-btn');
            if (!button) return;
            insertTemplatePlaceholder(button.dataset.placeholderKey);
        });

        document.getElementById('templateForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            templateBodyEditor?.sync?.();
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

        modalEl?.addEventListener('shown.bs.modal', () => {
            ensureTemplateBodyEditor();
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
            modal?.show();
            setTemplateBodyContent(template.body_html || '');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    try {
        await loadMeta();
        await load();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
};

const initCareers = async () => {
    const form = document.getElementById('careersForm');
    if (!form) return;

    let sectionsState = {};

    const previewLink = document.getElementById('careersPreviewLink');
    const publicUrlInput = document.getElementById('careersPublicUrl');
    const bannerPreview = document.getElementById('careersBannerPreview');

    const setNested = (obj, path, value) => {
        const keys = path.split('.');
        let cur = obj;
        keys.forEach((key, i) => {
            if (i === keys.length - 1) cur[key] = value;
            else cur = cur[key] = cur[key] || {};
        });
    };

    const getNested = (obj, path) => path.split('.').reduce((acc, key) => acc?.[key], obj);

    const rowHtml = (fields, values = {}, removeLabel = 'Remove') => `
        <div class="border rounded p-2 careers-dynamic-row">
            <div class="row g-2 align-items-end">
                ${fields.map((f) => `
                    <div class="col-md-${f.col || 3}">
                        <label class="form-label small mb-1">${f.label}</label>
                        <input type="text" class="form-control form-control-sm" data-field="${f.key}" value="${escapeHtml(values[f.key] || '')}" placeholder="${escapeHtml(f.placeholder || '')}">
                    </div>
                `).join('')}
                <div class="col-md-auto ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>${removeLabel}</button>
                </div>
            </div>
        </div>
    `;

    const collectRows = (container, keys) => Array.from(container?.querySelectorAll('.careers-dynamic-row') || []).map((row) => {
        const item = {};
        keys.forEach((key) => {
            item[key] = row.querySelector(`[data-field="${key}"]`)?.value?.trim() || '';
        });
        return item;
    }).filter((item) => Object.values(item).some(Boolean));

    const renderMarquee = (tags = []) => {
        const list = document.getElementById('careersMarqueeList');
        if (!list) return;
        list.innerHTML = (tags.length ? tags : ['']).map((tag) => rowHtml([{ label: 'Tag', key: 'tag', col: 10, placeholder: 'Engineering' }], { tag }, '×')).join('');
    };

    const renderWhyJoin = (items = []) => {
        const list = document.getElementById('careersWhyJoinList');
        if (!list) return;
        const rows = items.length ? items : [{ title: '', description: '', icon: 'star' }];
        list.innerHTML = rows.map((item) => rowHtml([
            { label: 'Title', key: 'title', col: 3 },
            { label: 'Description', key: 'description', col: 5 },
            { label: 'Icon', key: 'icon', col: 2, placeholder: 'lightbulb' },
        ], item)).join('');
    };

    const renderStats = (items = []) => {
        const list = document.getElementById('careersStatsList');
        if (!list) return;
        const rows = items.length ? items : [{ value: '', suffix: '', label: '' }];
        list.innerHTML = rows.map((item) => rowHtml([
            { label: 'Value', key: 'value', col: 2 },
            { label: 'Suffix', key: 'suffix', col: 2 },
            { label: 'Label', key: 'label', col: 5 },
        ], item)).join('');
    };

    const renderTestimonials = (items = []) => {
        const list = document.getElementById('careersTestimonialsList');
        if (!list) return;
        const rows = items.length ? items : [{ name: '', role: '', quote: '', photo_url: '' }];
        list.innerHTML = rows.map((item) => rowHtml([
            { label: 'Name', key: 'name', col: 2 },
            { label: 'Role', key: 'role', col: 2 },
            { label: 'Quote', key: 'quote', col: 4 },
            { label: 'Photo URL', key: 'photo_url', col: 3 },
        ], item)).join('');
    };

    const renderBadges = (items = []) => {
        const list = document.getElementById('careersBadgesList');
        if (!list) return;
        const rows = items.length ? items : [{ title: '', image_url: '' }];
        list.innerHTML = rows.map((item) => rowHtml([
            { label: 'Title', key: 'title', col: 4 },
            { label: 'Image URL', key: 'image_url', col: 6 },
        ], item)).join('');
    };

    const renderSocial = (items = []) => {
        const list = document.getElementById('careersSocialList');
        if (!list) return;
        const rows = items.length ? items : [{ platform: '', url: '' }];
        list.innerHTML = rows.map((item) => rowHtml([
            { label: 'Platform', key: 'platform', col: 3 },
            { label: 'URL', key: 'url', col: 7 },
        ], item)).join('');
    };

    const populateSectionTitles = (sections) => {
        document.querySelectorAll('[data-section-title]').forEach((input) => {
            input.value = getNested(sections, `section_titles.${input.dataset.sectionTitle}`) || '';
        });
    };

    const collectSections = () => {
        const sectionTitles = {};
        document.querySelectorAll('[data-section-title]').forEach((input) => {
            setNested(sectionTitles, input.dataset.sectionTitle, input.value);
        });

        return {
            ...sectionsState,
            marquee_tags: collectRows(document.getElementById('careersMarqueeList'), ['tag']).map((r) => r.tag),
            why_join: collectRows(document.getElementById('careersWhyJoinList'), ['title', 'description', 'icon']),
            stats: collectRows(document.getElementById('careersStatsList'), ['value', 'suffix', 'label']),
            testimonials: collectRows(document.getElementById('careersTestimonialsList'), ['name', 'role', 'quote', 'photo_url']),
            badges: collectRows(document.getElementById('careersBadgesList'), ['title', 'image_url']),
            social_links: collectRows(document.getElementById('careersSocialList'), ['platform', 'url']),
            footer_note: document.getElementById('careersFooterNote')?.value || '',
            section_titles: sectionTitles,
            show_marquee: true,
            show_stats: true,
            show_why_join: true,
            show_testimonials: true,
            show_badges: true,
        };
    };

    const populate = (settings) => {
        sectionsState = settings.sections || {};

        document.getElementById('careersHeroTitle').value = settings.hero_title || '';
        document.getElementById('careersHeroSubtitle').value = settings.hero_subtitle || '';
        document.getElementById('careersHeroCtaText').value = settings.hero_cta_text || '';
        document.getElementById('careersHeroCtaUrl').value = settings.hero_cta_url || '';
        document.getElementById('careersAboutHtml').value = settings.about_html || '';
        document.getElementById('careersFooterHtml').value = settings.footer_html || '';
        document.getElementById('careersMetaTitle').value = settings.meta_title || '';
        document.getElementById('careersMetaDescription').value = settings.meta_description || '';
        document.getElementById('careersThemePrimary').value = settings.theme_primary || '#0f172a';
        document.getElementById('careersThemeAccent').value = settings.theme_accent || '#2563eb';
        document.getElementById('careersIsPublished').checked = Boolean(settings.is_published);
        document.getElementById('careersFooterNote').value = sectionsState.footer_note || '';

        if (publicUrlInput) publicUrlInput.value = settings.public_url || '';
        if (previewLink) {
            previewLink.href = settings.public_url || '#';
            previewLink.classList.toggle('disabled', !settings.public_url);
            previewLink.textContent = settings.is_published ? 'Preview Live Page' : 'Preview Draft';
            previewLink.title = settings.is_published
                ? 'View the public careers page'
                : 'Draft preview (log in to HRMS in this browser). Publish to make it public.';
        }
        const publishHint = document.getElementById('careersPublishHint');
        if (publishHint) {
            publishHint.textContent = settings.is_published
                ? 'Your careers page is live at the public URL.'
                : 'The public URL shows “coming soon” until you publish. You can still preview the draft while logged in.';
        }

        if (bannerPreview) {
            bannerPreview.innerHTML = settings.banner_url
                ? `<img src="${escapeHtml(settings.banner_url)}" alt="Banner preview" class="img-fluid rounded border" style="max-height: 160px;">`
                : '';
        }

        renderMarquee(sectionsState.marquee_tags || []);
        renderWhyJoin(sectionsState.why_join || []);
        renderStats(sectionsState.stats || []);
        renderTestimonials(sectionsState.testimonials || []);
        renderBadges(sectionsState.badges || []);
        renderSocial(sectionsState.social_links || []);
        populateSectionTitles(sectionsState);
    };

    document.getElementById('careersAddMarquee')?.addEventListener('click', () => {
        renderMarquee([...(sectionsState.marquee_tags || []), '']);
        sectionsState.marquee_tags = collectRows(document.getElementById('careersMarqueeList'), ['tag']).map((r) => r.tag);
    });
    document.getElementById('careersAddWhyJoin')?.addEventListener('click', () => renderWhyJoin([...collectRows(document.getElementById('careersWhyJoinList'), ['title', 'description', 'icon']), { title: '', description: '', icon: 'star' }]));
    document.getElementById('careersAddStat')?.addEventListener('click', () => renderStats([...collectRows(document.getElementById('careersStatsList'), ['value', 'suffix', 'label']), { value: '', suffix: '', label: '' }]));
    document.getElementById('careersAddTestimonial')?.addEventListener('click', () => renderTestimonials([...collectRows(document.getElementById('careersTestimonialsList'), ['name', 'role', 'quote', 'photo_url']), { name: '', role: '', quote: '', photo_url: '' }]));
    document.getElementById('careersAddBadge')?.addEventListener('click', () => renderBadges([...collectRows(document.getElementById('careersBadgesList'), ['title', 'image_url']), { title: '', image_url: '' }]));
    document.getElementById('careersAddSocial')?.addEventListener('click', () => renderSocial([...collectRows(document.getElementById('careersSocialList'), ['platform', 'url']), { platform: '', url: '' }]));

    form.addEventListener('click', (e) => {
        if (!e.target.matches('[data-remove-row]')) return;
        e.target.closest('.careers-dynamic-row')?.remove();
    });

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
        formData.append('hero_cta_text', document.getElementById('careersHeroCtaText').value);
        formData.append('hero_cta_url', document.getElementById('careersHeroCtaUrl').value);
        formData.append('about_html', document.getElementById('careersAboutHtml').value);
        formData.append('footer_html', document.getElementById('careersFooterHtml').value);
        formData.append('meta_title', document.getElementById('careersMetaTitle').value);
        formData.append('meta_description', document.getElementById('careersMetaDescription').value);
        formData.append('theme_primary', document.getElementById('careersThemePrimary').value);
        formData.append('theme_accent', document.getElementById('careersThemeAccent').value);
        formData.append('is_published', document.getElementById('careersIsPublished').checked ? '1' : '0');
        formData.append('sections', JSON.stringify(collectSections()));

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
        jobs: initJobs,
        candidates: initCandidates,
        interviews: initInterviews,
        offers: initOffers,
        templates: initTemplates,
        careers: initCareers,
    };

    inits[page]?.();
});
