import api, { getErrorMessage } from './api';
import { renderDateTimeStackFromLabel } from './datetime-utils';

const routes = () => window.HRMS_WEB_ROUTES || {};

const statusClass = (status) => ({
    open: 'company-status-pill--inactive',
    in_progress: 'company-status-pill--warning',
    resolved: 'company-status-pill--active',
    closed: 'company-status-pill--cancelled',
}[status] || '');

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('helpdeskShowCard');
    const ticketId = card?.dataset.ticketId;
    const alertBox = document.getElementById('helpdeskShowAlert');
    const details = document.getElementById('helpdeskShowDetails');
    const commentsEl = document.getElementById('helpdeskComments');
    const commentForm = document.getElementById('helpdeskCommentForm');
    const commentBody = document.getElementById('helpdeskCommentBody');
    const internalWrap = document.getElementById('helpdeskInternalWrap');
    const internalNote = document.getElementById('helpdeskInternalNote');
    const manageCard = document.getElementById('helpdeskManageCard');
    const statusForm = document.getElementById('helpdeskStatusForm');
    const statusSelect = document.getElementById('helpdeskStatusSelect');
    const backBtn = document.getElementById('helpdeskShowBackBtn');
    let ticket = null;
    let meta = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    backBtn?.addEventListener('click', () => {
        window.location.href = routes().helpdeskIndex || '/helpdesk';
    });

    const renderDetails = () => {
        if (!ticket) return;

        const attachments = (ticket.attachments || []).map((file) => `
            <li><a href="${escapeHtml(file.file_url)}" target="_blank" rel="noopener">${escapeHtml(file.original_name)}</a></li>
        `).join('') || '<li class="text-muted">No attachments</li>';

        details.innerHTML = `
            <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                    <div class="text-muted small">${escapeHtml(ticket.ticket_number)}</div>
                    <h2 class="h4 mb-2">${escapeHtml(ticket.subject)}</h2>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="company-status-pill ${statusClass(ticket.status)}">${escapeHtml(ticket.status_label)}</span>
                        <span class="badge bg-light text-dark border">${escapeHtml(ticket.category_label)}</span>
                        <span class="badge bg-light text-dark border">${escapeHtml(ticket.priority_label)} priority</span>
                    </div>
                </div>
                <div class="text-end small text-muted">
                    <div>Created ${renderDateTimeStackFromLabel(ticket.created_at_label)}</div>
                    <div>Updated ${renderDateTimeStackFromLabel(ticket.updated_at_label)}</div>
                </div>
            </div>
            <div class="row g-3 small">
                <div class="col-md-4"><span class="text-muted">Employee:</span> ${escapeHtml(ticket.employee?.full_name || '—')}</div>
                <div class="col-md-4"><span class="text-muted">Raised by:</span> ${escapeHtml(ticket.created_by?.name || '—')}</div>
                <div class="col-md-4"><span class="text-muted">Assigned to:</span> ${escapeHtml(ticket.assigned_to?.name || 'Unassigned')}</div>
            </div>
            <hr>
            <p class="mb-3">${escapeHtml(ticket.description).replace(/\n/g, '<br>')}</p>
            <div class="small">
                <span class="text-muted">Attachments</span>
                <ul class="mb-0 ps-3">${attachments}</ul>
            </div>
        `;
    };

    const renderComments = () => {
        const comments = ticket?.comments || [];
        if (!comments.length) {
            commentsEl.innerHTML = '<div class="text-muted">No replies yet.</div>';
            return;
        }

        commentsEl.innerHTML = comments.map((comment) => `
            <div class="border rounded p-3 ${comment.is_internal ? 'bg-light' : ''}">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <strong>${escapeHtml(comment.user?.name || 'System')}</strong>
                    <span class="small text-muted">${renderDateTimeStackFromLabel(comment.created_at_label, { empty: '' })}${comment.is_internal ? ' · Internal note' : ''}</span>
                </div>
                <div>${escapeHtml(comment.body).replace(/\n/g, '<br>')}</div>
            </div>
        `).join('');
    };

    const loadMeta = async () => {
        const { data } = await api.get('/helpdesk-tickets/meta');
        meta = data.data;
        statusSelect.innerHTML = (meta.statuses || []).map((item) => `<option value="${item.value}">${item.label}</option>`).join('');
    };

    const loadTicket = async () => {
        const { data } = await api.get(`/helpdesk-tickets/${ticketId}`);
        ticket = data.data.ticket;
        renderDetails();
        renderComments();

        if (ticket.can_manage) {
            manageCard?.classList.remove('d-none');
            statusSelect.value = ticket.status;
            internalWrap?.classList.remove('d-none');
        }

        if (ticket.can_comment) {
            commentForm?.classList.remove('d-none');
        } else {
            commentForm?.classList.add('d-none');
        }
    };

    commentForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api.post(`/helpdesk-tickets/${ticketId}/comments`, {
                body: commentBody.value.trim(),
                is_internal: internalNote?.checked || false,
            });
            commentBody.value = '';
            if (internalNote) internalNote.checked = false;
            showAlert('Reply posted.');
            await loadTicket();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    statusForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await api.patch(`/helpdesk-tickets/${ticketId}/status`, {
                status: statusSelect.value,
            });
            showAlert('Ticket status updated.');
            await loadTicket();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    });

    try {
        await loadMeta();
        await loadTicket();
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
