import api, { getErrorMessage } from './api';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const formatChanges = (oldValues, newValues) => {
    if (!newValues || typeof newValues !== 'object') {
        return '—';
    }

    return Object.keys(newValues).map((key) => (
        `${key}: ${oldValues?.[key] ?? '—'} → ${newValues[key] ?? '—'}`
    )).join('; ') || '—';
};

const formatTime = (iso) => {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return escapeHtml(iso);
    }

    return escapeHtml(date.toLocaleString());
};

const statusBadge = (status) => {
    const normalized = status === 'failure' ? 'failure' : 'success';
    const label = normalized === 'failure' ? 'Failure' : 'Success';
    const className = normalized === 'failure' ? 'bg-danger' : 'bg-success';

    return `<span class="badge ${className}">${label}</span>`;
};

document.addEventListener('DOMContentLoaded', async () => {
    const alertBox = document.getElementById('activityLogsAlert');
    const companySelect = document.getElementById('activityLogCompanyId');
    const dateInput = document.getElementById('activityLogDate');
    const moduleSelect = document.getElementById('activityLogModule');
    const statusSelect = document.getElementById('activityLogStatus');
    const searchInput = document.getElementById('activityLogSearch');
    const tableBody = document.getElementById('activityLogsTableBody');
    const summaryEl = document.getElementById('activityLogsSummary');
    const titleEl = document.getElementById('activityLogsTitle');
    const paginationInfo = document.getElementById('activityLogsPaginationInfo');
    const paginationList = document.getElementById('activityLogsPaginationList');
    const perPageSelect = document.getElementById('activityLogsPerPage');
    const loadBtn = document.getElementById('loadActivityLogsBtn');

    const isSuperAdmin = Boolean(companySelect);
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect, 50);

    dateInput.value = new Date().toISOString().slice(0, 10);

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const buildParams = () => {
        const params = {
            date: dateInput.value,
            page: currentPage,
            per_page: currentPerPage,
        };

        if (moduleSelect.value) {
            params.module = moduleSelect.value;
        }

        if (statusSelect.value) {
            params.status = statusSelect.value;
        }

        if (searchInput.value.trim()) {
            params.search = searchInput.value.trim();
        }

        if (isSuperAdmin && companySelect.value) {
            params.company_id = companySelect.value;
        }

        return params;
    };

    const renderPagination = (pagination) => {
        renderListPagination({
            infoEl: paginationInfo,
            listEl: paginationList,
            perPageSelectEl: perPageSelect,
            pagination,
            itemLabel: 'entries',
            emptyMessage: 'No entries',
        });
    };

    const renderRows = (entries) => {
        if (!entries.length) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No activity logs found for the selected filters.</td></tr>';
            return;
        }

        tableBody.innerHTML = entries.map((entry) => {
            const userLabel = entry.user_name
                ? `${entry.user_name}${entry.user_email ? ` (${entry.user_email})` : ''}`
                : (entry.user_email || '—');

            return `
                <tr>
                    <td class="text-nowrap">${formatTime(entry.logged_at)}</td>
                    <td>${escapeHtml(userLabel)}</td>
                    <td>${escapeHtml(entry.module || '—')}</td>
                    <td><code class="small">${escapeHtml(entry.action || '—')}</code></td>
                    <td>${statusBadge(entry.status)}</td>
                    <td>${escapeHtml(entry.message || '—')}${entry.action_note ? `<div class="small text-muted">${escapeHtml(entry.action_note)}</div>` : ''}</td>
                    <td class="small">${escapeHtml(formatChanges(entry.old_values, entry.new_values))}</td>
                    <td class="text-danger">${escapeHtml(entry.failure_reason || '—')}</td>
                    <td class="text-muted small">${escapeHtml(entry.request?.ip || '—')}</td>
                </tr>
            `;
        }).join('');
    };

    const loadLogs = async () => {
        if (isSuperAdmin && !companySelect.value) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Select a company to view activity logs.</td></tr>';
            summaryEl.textContent = '';
            paginationInfo.textContent = '';
            paginationList.innerHTML = '';
            return;
        }

        tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading activity logs…</td></tr>';

        try {
            const { data } = await api.get('/activity-logs', { params: buildParams() });
            const payload = data.data || data;
            const entries = payload.entries || [];
            const pagination = payload.pagination || {};

            titleEl.textContent = `Activity log entries — ${payload.date || dateInput.value}`;
            summaryEl.textContent = `${pagination.total || 0} entries for this day`;
            renderRows(entries);
            renderPagination(pagination);
        } catch (error) {
            showAlert(getErrorMessage(error, 'Unable to load activity logs.'));
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Failed to load activity logs.</td></tr>';
        }
    };

    const loadCompanies = async () => {
        if (!isSuperAdmin) {
            return;
        }

        try {
            const { data } = await api.get('/companies', { params: { per_page: 50 } });
            const companies = data.data?.companies || [];

            companySelect.innerHTML = '<option value="">Select company…</option>' + companies.map((company) => (
                `<option value="${company.id}">${escapeHtml(company.name)}</option>`
            )).join('');
        } catch (error) {
            showAlert(getErrorMessage(error, 'Unable to load companies.'));
        }
    };

    const resetAndLoad = () => {
        currentPage = 1;
        loadLogs();
    };

    bindPagination(paginationList, (page) => {
        currentPage = page;
        loadLogs();
    });
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        resetAndLoad();
    });

    [dateInput, moduleSelect, statusSelect].forEach((element) => {
        element?.addEventListener('change', resetAndLoad);
    });

    companySelect?.addEventListener('change', resetAndLoad);

    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            resetAndLoad();
        }
    });

    loadBtn?.addEventListener('click', resetAndLoad);

    if (isSuperAdmin) {
        await loadCompanies();
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Select a company to view activity logs.</td></tr>';
    } else {
        await loadLogs();
    }
});
