import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { renderDateTimeStackFromLabel } from './datetime-utils';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { composeActionGroup, renderViewIconButton } from './action-icons';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const statusClass = (status) => ({
    scheduled: 'company-status-pill--warning',
    completed: 'company-status-pill--active',
    cancelled: 'company-status-pill--cancelled',
}[status] || '');

document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = document.getElementById('oneOnOnePageRoot');
    const tableBody = document.getElementById('meetingsTableBody');
    if (!pageRoot || !tableBody) return;

    const canSchedule = pageRoot.dataset.canSchedule === '1';
    let scheduleModal;
    let detailModal;
    let currentMeetingId = null;
    let canManageCurrentMeeting = false;
    let currentPage = 1;
    const paginationInfo = document.getElementById('meetingsPaginationInfo');
    const paginationList = document.getElementById('meetingsPaginationList');
    const perPageSelect = document.getElementById('meetingsPerPage');
    let currentPerPage = readPerPage(perPageSelect);

    const showAlert = (message, type = 'success') => {
        const alertBox = document.getElementById('performanceAlert');
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const defaultDateTime = () => {
        const date = new Date();
        date.setMinutes(date.getMinutes() + 60 - (date.getMinutes() % 15));
        date.setSeconds(0, 0);
        return date.toISOString().slice(0, 16);
    };

    const meetCell = (meeting) => {
        const link = meeting.meeting_link;

        if (link && meeting.meeting_link_valid) {
            return `<a href="${escapeHtml(link)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success">Join</a>`;
        }

        if (link && !meeting.meeting_link_valid) {
            return '<span class="badge text-bg-danger">Invalid link</span>';
        }

        return '<span class="text-muted">—</span>';
    };

    const loadMeetings = async (page = currentPage) => {
        currentPage = page;
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr>';

        const params = {
            page,
            per_page: currentPerPage,
            status: document.getElementById('meetingStatusFilter')?.value || undefined,
            search: document.getElementById('meetingSearchFilter')?.value?.trim() || undefined,
        };

        const { data } = await api.get('/performance/one-on-one', { params });
        const meetings = data.data.meetings || [];

        if (!meetings.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5">No one-on-one meetings found.</td></tr>';
        } else {
            tableBody.innerHTML = meetings.map((item) => `
                <tr>
                    <td>
                        <span class="fw-semibold">${escapeHtml(item.title)}</span>
                        <div class="small text-muted">With ${escapeHtml(item.organizer?.name || 'Organizer')}</div>
                    </td>
                    <td>
                        ${escapeHtml(item.employee?.full_name || '—')}
                        <div class="small text-muted">${escapeHtml(item.employee?.employee_code || '')}</div>
                    </td>
                    <td>${renderDateTimeStackFromLabel(item.scheduled_at_label)}</td>
                    <td>${item.duration_minutes || 30} min</td>
                    <td>${meetCell(item)}</td>
                    <td><span class="company-status-pill ${statusClass(item.status)}">${escapeHtml(item.status_label || item.status)}</span></td>
                    <td class="text-end">${composeActionGroup({
                        view: renderViewIconButton('data-view-meeting', item.id, 'View meeting'),
                    })}</td>
                </tr>
            `).join('');

            tableBody.querySelectorAll('[data-view-meeting]').forEach((btn) => {
                btn.addEventListener('click', () => openMeetingDetail(Number(btn.dataset.viewMeeting)));
            });
        }

        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: perPageSelect,
            pagination: data.data.pagination,
            itemLabel: 'meetings',
            emptyMessage: 'No one-on-one meetings found',
        });
    };

    const renderActionItemsEditor = (items = []) => {
        const rows = (items.length ? items : [{ text: '', is_done: false }]).map((item, index) => `
            <div class="input-group mb-2 meeting-action-item-row" data-index="${index}">
                <div class="input-group-text">
                    <input class="form-check-input mt-0 meeting-action-done" type="checkbox" ${item.is_done ? 'checked' : ''}>
                </div>
                <input type="text" class="form-control meeting-action-text" value="${escapeHtml(item.text || '')}" placeholder="Action item">
                <button type="button" class="btn btn-outline-secondary meeting-action-remove" ${items.length <= 1 ? 'disabled' : ''}>Remove</button>
            </div>
        `).join('');

        return `
            <div id="meetingActionItemsEditor">${rows}</div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="meetingAddActionItemBtn">Add action item</button>
        `;
    };

    const collectActionItems = () => Array.from(document.querySelectorAll('.meeting-action-item-row'))
        .map((row) => ({
            text: row.querySelector('.meeting-action-text')?.value?.trim() || '',
            is_done: row.querySelector('.meeting-action-done')?.checked || false,
        }))
        .filter((item) => item.text);

    const bindActionItemEditor = () => {
        document.getElementById('meetingAddActionItemBtn')?.addEventListener('click', () => {
            const editor = document.getElementById('meetingActionItemsEditor');
            if (!editor) return;
            editor.insertAdjacentHTML('beforeend', `
                <div class="input-group mb-2 meeting-action-item-row">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0 meeting-action-done" type="checkbox">
                    </div>
                    <input type="text" class="form-control meeting-action-text" placeholder="Action item">
                    <button type="button" class="btn btn-outline-secondary meeting-action-remove">Remove</button>
                </div>
            `);
            bindActionItemEditor();
        });

        document.querySelectorAll('.meeting-action-remove').forEach((btn) => {
            btn.replaceWith(btn.cloneNode(true));
        });

        document.querySelectorAll('.meeting-action-remove').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.target.closest('.meeting-action-item-row')?.remove();
            });
        });
    };

    const renderMeetingDetail = (meeting) => {
        const body = document.getElementById('meetingDetailBody');
        const footer = document.getElementById('meetingDetailFooter');
        if (!body || !footer) return;

        document.getElementById('meetingDetailModalLabel').textContent = meeting.title;

        const link = meeting.meeting_link;
        const meetButtons = link && meeting.meeting_link_valid
            ? `<a href="${escapeHtml(link)}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm">Join meeting</a>`
            : '';
        const calendarButton = meeting.google_calendar_link
            ? `<a href="${escapeHtml(meeting.google_calendar_link)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">Add to Google Calendar</a>`
            : '';
        const invalidMeetWarning = link && !meeting.meeting_link_valid
            ? '<div class="col-12"><div class="alert alert-warning py-2 mb-0">The saved meeting link looks invalid. Paste a valid https link below.</div></div>'
            : '';

        const meetLinkEditor = canManageCurrentMeeting && meeting.status === 'scheduled'
            ? `
                <div class="col-12">
                    <label for="meetingMeetLinkInput" class="form-label">Meeting link</label>
                    <div class="input-group">
                        <input type="url" class="form-control" id="meetingMeetLinkInput" value="${escapeHtml(link || '')}" placeholder="https://zoom.us/j/..., https://teams.microsoft.com/...">
                        <button type="button" class="btn btn-outline-primary" id="meetingSaveMeetLinkBtn">Save link</button>
                    </div>
                    <div class="form-text">Zoom, Microsoft Teams, Google Meet, or any other https meeting URL.</div>
                </div>
            `
            : (link && meeting.meeting_link_valid
                ? `<div class="col-12"><div class="small text-muted">Meeting link</div><a href="${escapeHtml(link)}" target="_blank" rel="noopener noreferrer">${escapeHtml(link)}</a></div>`
                : '');

        body.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted">Employee</div>
                    <div class="fw-semibold">${escapeHtml(meeting.employee?.full_name || '—')}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Organizer</div>
                    <div class="fw-semibold">${escapeHtml(meeting.organizer?.name || '—')}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Scheduled</div>
                    <div>${renderDateTimeStackFromLabel(meeting.scheduled_at_label)} (${meeting.duration_minutes || 30} min)</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Status</div>
                    <div><span class="company-status-pill ${statusClass(meeting.status)}">${escapeHtml(meeting.status_label || meeting.status)}</span></div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    ${meetButtons}
                    ${calendarButton}
                </div>
                ${invalidMeetWarning}
                ${meetLinkEditor}
                <div class="col-12">
                    <div class="small text-muted mb-1">Agenda</div>
                    <div class="border rounded p-3 bg-light">${meeting.agenda ? escapeHtml(meeting.agenda) : '<span class="text-muted">No agenda provided.</span>'}</div>
                </div>
                <div class="col-12">
                    <label for="meetingNotes" class="form-label">Meeting notes</label>
                    <textarea class="form-control" id="meetingNotes" rows="4" maxlength="10000" ${canManageCurrentMeeting && meeting.status === 'scheduled' ? '' : 'readonly'}>${escapeHtml(meeting.meeting_notes || '')}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Action items</label>
                    ${canManageCurrentMeeting && meeting.status === 'scheduled'
                        ? renderActionItemsEditor(meeting.action_items || [])
                        : `<div class="border rounded p-3 bg-light">${(meeting.action_items || []).length
                            ? (meeting.action_items || []).map((item) => `<div>${item.is_done ? '✓' : '○'} ${escapeHtml(item.text)}</div>`).join('')
                            : '<span class="text-muted">No action items.</span>'}</div>`}
                </div>
            </div>
        `;

        if (canManageCurrentMeeting && meeting.status === 'scheduled') {
            bindActionItemEditor();
        }

        footer.innerHTML = `
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            ${canManageCurrentMeeting && meeting.status === 'scheduled' ? `
                <button type="button" class="btn btn-outline-danger" id="meetingCancelBtn">Cancel meeting</button>
                <button type="button" class="btn btn-primary" id="meetingCompleteBtn">Mark completed</button>
            ` : ''}
        `;

        document.getElementById('meetingCancelBtn')?.addEventListener('click', () => {
            cancelMeeting(meeting.id).catch((error) => showAlert(getErrorMessage(error), 'danger'));
        });

        document.getElementById('meetingCompleteBtn')?.addEventListener('click', () => {
            completeMeeting(meeting.id).catch((error) => showAlert(getErrorMessage(error), 'danger'));
        });

        document.getElementById('meetingSaveMeetLinkBtn')?.addEventListener('click', () => {
            saveMeetLink(meeting.id).catch((error) => showAlert(getErrorMessage(error), 'danger'));
        });
    };

    const saveMeetLink = async (meetingId) => {
        const link = document.getElementById('meetingMeetLinkInput')?.value?.trim() || '';

        if (!link) {
            showAlert('Paste a meeting link before saving.', 'danger');
            return;
        }

        const { data } = await api.patch(`/performance/one-on-one/${meetingId}/meet-link`, {
            meeting_link: link,
        });

        showAlert(data.message || 'Meeting link saved.');
        await openMeetingDetail(meetingId);
        await loadMeetings(1);
    };

    const openMeetingDetail = async (meetingId) => {
        currentMeetingId = meetingId;
        const body = document.getElementById('meetingDetailBody');
        if (body) body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';

        detailModal?.show();

        const { data } = await api.get(`/performance/one-on-one/${meetingId}`);
        const meeting = data.data.meeting;
        canManageCurrentMeeting = !!meeting.can_manage;
        renderMeetingDetail(meeting);
    };

    const completeMeeting = async (meetingId) => {
        await api.patch(`/performance/one-on-one/${meetingId}/complete`, {
            meeting_notes: document.getElementById('meetingNotes')?.value || '',
            action_items: collectActionItems(),
        });
        detailModal?.hide();
        showAlert('Meeting marked as completed.');
        await loadMeetings(1);
    };

    const cancelMeeting = async (meetingId) => {
        if (!window.confirm('Cancel this one-on-one meeting?')) return;
        await api.patch(`/performance/one-on-one/${meetingId}/cancel`);
        detailModal?.hide();
        showAlert('Meeting cancelled.');
        await loadMeetings(1);
    };

    if (canSchedule) {
        const scheduleModalEl = document.getElementById('meetingScheduleModal');
        scheduleModal = scheduleModalEl ? Modal.getOrCreateInstance(scheduleModalEl) : null;

        bindEmployeeSearchSelect({
            inputId: 'meetingEmployeeSearch',
            hiddenId: 'meetingEmployeeId',
        });

        document.getElementById('scheduleMeetingBtn')?.addEventListener('click', () => {
            document.getElementById('meetingScheduleForm')?.reset();
            document.getElementById('meetingTitle').value = 'One-on-one Meeting';
            document.getElementById('meetingDuration').value = '30';
            document.getElementById('meetingScheduledAt').value = defaultDateTime();
            scheduleModal?.show();
        });

        document.getElementById('meetingScheduleForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const employeeId = document.getElementById('meetingEmployeeId')?.value;
            if (!employeeId) {
                showAlert('Please select a team member.', 'danger');
                return;
            }

            try {
                const meetingLink = document.getElementById('meetingLink')?.value?.trim() || undefined;

                const { data } = await api.post('/performance/one-on-one', {
                    title: document.getElementById('meetingTitle').value.trim(),
                    employee_id: Number(employeeId),
                    scheduled_at: document.getElementById('meetingScheduledAt').value,
                    duration_minutes: Number(document.getElementById('meetingDuration').value),
                    agenda: document.getElementById('meetingAgenda').value.trim() || undefined,
                    meeting_link: meetingLink,
                });

                scheduleModal?.hide();
                showAlert(data.message || 'One-on-one meeting scheduled.');
                await loadMeetings(1);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    }

    const detailModalEl = document.getElementById('meetingDetailModal');
    detailModal = detailModalEl ? Modal.getOrCreateInstance(detailModalEl) : null;

    document.getElementById('meetingStatusFilter')?.addEventListener('change', () => {
        loadMeetings(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    document.getElementById('meetingSearchFilter')?.addEventListener('input', () => {
        clearTimeout(window.__meetingSearchTimer);
        window.__meetingSearchTimer = setTimeout(() => {
            loadMeetings(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
        }, 300);
    });

    bindPagination(paginationList, (page) => {
        loadMeetings(page).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadMeetings(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    loadMeetings(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
});
