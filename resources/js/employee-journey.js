import api, { getErrorMessage } from './api';
import {
    buildRangeQueryParams,
    formatDisplayDate,
    monthStartDateInput,
    resolveClientDateRange,
    todayDateInput,
} from './date-range-utils';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';
const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const markerClass = (marker) => {
    if (marker === 'failure') {
        return 'is-failure';
    }

    if (marker === 'milestone') {
        return 'is-milestone';
    }

    if (marker === 'approved') {
        return 'is-approved';
    }

    if (marker === 'rejected') {
        return 'is-rejected';
    }

    if (marker === 'pending') {
        return 'is-pending';
    }

    return '';
};

const statusBadge = (label, marker) => {
    if (!label) {
        return '';
    }

    const className = marker === 'approved'
        ? 'text-bg-success'
        : marker === 'rejected'
            ? 'text-bg-danger'
            : marker === 'pending'
                ? 'text-bg-warning'
                : marker === 'milestone'
                    ? 'text-bg-primary'
                    : 'text-bg-light text-dark';

    return `<span class="badge ${className}">${escapeHtml(label)}</span>`;
};

const renderDetails = (details = {}) => {
    const rows = Object.entries(details).filter(([, value]) => value !== null && value !== '');

    if (!rows.length) {
        return '';
    }

    return `
        <dl class="employee-journey-details mb-0 mt-2">
            ${rows.map(([label, value]) => `
                <div class="employee-journey-details-row">
                    <dt>${escapeHtml(label)}</dt>
                    <dd>${escapeHtml(value)}</dd>
                </div>
            `).join('')}
        </dl>
    `;
};

export const renderEmployeeJourney = (container, entries = []) => {
    if (!container) {
        return;
    }

    if (!entries.length) {
        container.innerHTML = '<div class="text-muted py-4 text-center">No journey events found for this period.</div>';
        return;
    }

    container.innerHTML = entries.map((entry) => `
        <div class="activity-timeline-item">
            <div class="activity-timeline-marker ${markerClass(entry.marker)}" aria-hidden="true"></div>
            <div class="activity-timeline-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <strong>${escapeHtml(entry.title)}</strong>
                            ${statusBadge(entry.status_label, entry.marker)}
                        </div>
                        <div class="text-muted small">${escapeHtml(entry.subtitle || entry.category || '')}</div>
                    </div>
                    <span class="text-muted small">${formatDateTime(entry.occurred_at)}</span>
                </div>
                <div class="text-muted small mt-1">
                    ${entry.actor?.name ? escapeHtml(entry.actor.name) : 'System'}
                    ${entry.actor?.email ? ` · ${escapeHtml(entry.actor.email)}` : ''}
                </div>
                ${entry.note ? `<div class="small mt-2"><strong>Note:</strong> ${escapeHtml(entry.note)}</div>` : ''}
                ${renderDetails(entry.details)}
            </div>
        </div>
    `).join('');
};

export const initEmployeeJourneyTab = ({
    tabId,
    endpoint,
    listId = 'employeeJourneyList',
    rangePresetId = 'employeeJourneyRangePreset',
    customRangeId = 'employeeJourneyCustomRange',
    fromDateId = 'employeeJourneyFromDate',
    toDateId = 'employeeJourneyToDate',
    applyRangeId = 'employeeJourneyApplyRangeBtn',
    refreshId = 'employeeJourneyRefreshBtn',
    rangeSummaryId = 'employeeJourneyRangeSummary',
    paginationWrapId = 'employeeJourneyPagination',
    paginationInfoId = 'employeeJourneyPaginationInfo',
    paginationListId = 'employeeJourneyPaginationList',
    perPageId = 'employeeJourneyPerPage',
}) => {
    const tab = document.getElementById(tabId);
    const container = document.getElementById(listId);
    const rangePresetEl = document.getElementById(rangePresetId);
    const customRangeWrap = document.getElementById(customRangeId);
    const fromDateEl = document.getElementById(fromDateId);
    const toDateEl = document.getElementById(toDateId);
    const applyRangeBtn = document.getElementById(applyRangeId);
    const refreshBtn = document.getElementById(refreshId);
    const rangeSummaryEl = document.getElementById(rangeSummaryId);
    const paginationWrap = document.getElementById(paginationWrapId);
    const paginationInfo = document.getElementById(paginationInfoId);
    const paginationList = document.getElementById(paginationListId);
    const perPageSelect = document.getElementById(perPageId);

    if (!tab || !container || !endpoint) {
        return;
    }

    let loaded = false;
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let currentRange = resolveClientDateRange('this_month');
    const updateRangeSummary = () => {
        if (!rangeSummaryEl) {
            return;
        }

        if (currentRange.preset === 'custom' && rangePresetEl?.value === 'custom') {
            rangeSummaryEl.textContent = 'Select from and to dates, then click Apply.';
            return;
        }

        const presetLabel = rangePresetEl?.selectedOptions?.[0]?.textContent?.trim() || 'This Month';
        rangeSummaryEl.textContent = currentRange.preset === 'custom'
            ? `Showing events from ${formatDisplayDate(currentRange.from_date)} to ${formatDisplayDate(currentRange.to_date)}`
            : `Showing events for ${presetLabel.toLowerCase()} (${formatDisplayDate(currentRange.from_date)} – ${formatDisplayDate(currentRange.to_date)})`;
    };

    const showCustomRangePicker = () => {
        currentRange = {
            preset: 'custom',
            from_date: fromDateEl?.value || currentRange.from_date || monthStartDateInput(),
            to_date: toDateEl?.value || currentRange.to_date || todayDateInput(),
        };

        customRangeWrap?.classList.remove('d-none');

        if (fromDateEl) {
            fromDateEl.value = currentRange.from_date;
        }

        if (toDateEl) {
            toDateEl.value = currentRange.to_date;
        }

        updateRangeSummary();
        fromDateEl?.focus();
    };

    const syncRangeControls = () => {
        if (rangePresetEl) {
            rangePresetEl.value = currentRange.preset;
        }

        customRangeWrap?.classList.toggle('d-none', currentRange.preset !== 'custom');

        if (fromDateEl) {
            fromDateEl.value = currentRange.from_date || monthStartDateInput();
        }

        if (toDateEl) {
            toDateEl.value = currentRange.to_date || todayDateInput();
        }

        updateRangeSummary();
    };

    const loadJourney = async (page = 1) => {
        currentPage = page;
        container.innerHTML = '<div class="text-muted py-4 text-center">Loading portal journey…</div>';

        try {
            const { data } = await api.get(endpoint, {
                params: {
                    ...buildRangeQueryParams(currentRange),
                    page,
                    per_page: currentPerPage,
                },
            });
            currentRange = data.data?.date_range || currentRange;
            syncRangeControls();
            renderEmployeeJourney(container, data.data?.entries || []);
            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                perPageSelectEl: perPageSelect,
                pagination: data.data?.pagination,
                itemLabel: 'events',
                emptyMessage: 'No journey events found for this period.',
            });
            loaded = true;
        } catch (error) {
            container.innerHTML = `<div class="text-danger py-4 text-center">${escapeHtml(getErrorMessage(error, 'Unable to load portal journey.'))}</div>`;
        }
    };

    const applyRangeSelection = async () => {
        const preset = rangePresetEl?.value || 'this_month';

        if (preset === 'custom') {
            if (!fromDateEl?.value || !toDateEl?.value) {
                container.innerHTML = '<div class="text-warning py-4 text-center">Select both from and to dates.</div>';
                return;
            }

            currentRange = resolveClientDateRange('custom', fromDateEl.value, toDateEl.value);
        } else {
            currentRange = resolveClientDateRange(preset);
        }

        customRangeWrap?.classList.toggle('d-none', preset !== 'custom');
        currentPage = 1;
        await loadJourney(1);
    };

    bindPagination(paginationWrap, (page) => loadJourney(page));
    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadJourney(1);
    });
    tab.addEventListener('shown.bs.tab', () => {
        if (!loaded) {
            loadJourney(1);
        }
    });
    rangePresetEl?.addEventListener('change', async () => {
        if (rangePresetEl.value === 'custom') {
            showCustomRangePicker();
            return;
        }

        await applyRangeSelection();
    });

    applyRangeBtn?.addEventListener('click', applyRangeSelection);
    refreshBtn?.addEventListener('click', () => loadJourney(currentPage));
    if (fromDateEl && !fromDateEl.value) {
        fromDateEl.value = monthStartDateInput();
    }

    if (toDateEl && !toDateEl.value) {
        toDateEl.value = todayDateInput();
    }
};
