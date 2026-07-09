import { Modal } from 'bootstrap';
import Chart from 'chart.js/auto';
import api, { getErrorMessage } from './api';
import {
    buildRangeQueryParams,
    formatDisplayDate,
    monthStartDateInput,
    resolveClientDateRange,
    todayDateInput,
} from './date-range-utils';

document.addEventListener('DOMContentLoaded', async () => {
    const chartInstances = new Map();

    const CHART_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#8b5cf6', '#64748b', '#14b8a6', '#ec4899'];

    const root = document.getElementById('homeDashboardRoot');
    const empty = document.getElementById('homeDashboardEmpty');
    const alertBox = document.getElementById('homeDashboardAlert');
    const manageBtn = document.getElementById('homeDashboardManageBtn');
    const widgetModalEl = document.getElementById('homeDashboardWidgetModal');
    const widgetOptions = document.getElementById('homeDashboardWidgetOptions');
    const saveWidgetsBtn = document.getElementById('homeDashboardSaveWidgetsBtn');
    const widgetModal = widgetModalEl ? Modal.getOrCreateInstance(widgetModalEl) : null;
    const rangePresetEl = document.getElementById('homeDashboardRangePreset');
    const customRangeWrap = document.getElementById('homeDashboardCustomRange');
    const fromDateEl = document.getElementById('homeDashboardFromDate');
    const toDateEl = document.getElementById('homeDashboardToDate');
    const applyRangeBtn = document.getElementById('homeDashboardApplyRangeBtn');
    const rangeSummaryEl = document.getElementById('homeDashboardRangeSummary');

    if (!root) return;

    let availableWidgets = [];
    let activeWidgets = [];
    let loadRequestId = 0;
    let customRangePending = false;
    let currentRange = resolveClientDateRange('this_month');
    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const rangeParams = () => buildRangeQueryParams(currentRange);

    const isCustomPickerActive = () => customRangePending || rangePresetEl?.value === 'custom';

    const updateRangeSummary = () => {
        if (!rangeSummaryEl) return;

        if (isCustomPickerActive() && currentRange.preset !== 'custom') {
            rangeSummaryEl.textContent = 'Select from and to dates, then click Apply.';
            return;
        }

        const presetLabel = rangePresetEl?.selectedOptions?.[0]?.textContent?.trim() || 'This Month';
        rangeSummaryEl.textContent = currentRange.preset === 'custom'
            ? `Showing data from ${formatDisplayDate(currentRange.from_date)} to ${formatDisplayDate(currentRange.to_date)}`
            : `Showing data for ${presetLabel.toLowerCase()} (${formatDisplayDate(currentRange.from_date)} – ${formatDisplayDate(currentRange.to_date)})`;
    };

    const showCustomRangePicker = () => {
        customRangePending = true;

        if (rangePresetEl) {
            rangePresetEl.value = 'custom';
        }

        currentRange = resolveClientDateRange(
            'custom',
            fromDateEl?.value || currentRange.from_date || monthStartDateInput(),
            toDateEl?.value || currentRange.to_date || todayDateInput(),
        );

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
        if (isCustomPickerActive() && currentRange.preset !== 'custom') {
            customRangeWrap?.classList.remove('d-none');
            updateRangeSummary();
            return;
        }

        customRangePending = false;

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
    const destroyCharts = () => {
        chartInstances.forEach((chart) => chart.destroy());
        chartInstances.clear();
    };

    const renderChart = (canvas, widget) => {
        const catalog = widget.catalog || {};
        const data = widget.data || { labels: [], series: [] };
        const type = catalog.chart_type === 'bar' ? 'bar' : 'doughnut';
        const chartLabel = catalog.label || 'Count';
        const hasData = (data.series || []).some((value) => Number(value) > 0);

        if (!hasData) {
            const parent = canvas.parentElement;
            if (parent) {
                parent.innerHTML = `
                    <div class="home-dashboard-chart-empty text-center text-muted py-5">
                        <p class="mb-1">${data.meta?.error || 'No data for this period.'}</p>
                        ${data.meta?.chart_title ? `<p class="small mb-0">${data.meta.chart_title}</p>` : ''}
                    </div>
                `;
            }
            return;
        }

        const chart = new Chart(canvas, {
            type,
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: chartLabel,
                    data: data.series || [],
                    backgroundColor: CHART_COLORS,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type !== 'bar',
                        position: type === 'bar' ? 'top' : 'bottom',
                    },
                    title: {
                        display: Boolean(data.meta?.chart_title),
                        text: data.meta?.chart_title || '',
                        align: 'start',
                        font: { size: 13, weight: '600' },
                    },
                },
                scales: type === 'bar' ? {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                } : {},
            },
        });

        chartInstances.set(widget.key, chart);
    };

    const renderWidgets = () => {
        destroyCharts();

        if (!activeWidgets.length) {
            root.innerHTML = '';
            empty?.classList.remove('d-none');
            return;
        }

        empty?.classList.add('d-none');
        root.innerHTML = activeWidgets.map((widget) => {
            const catalog = widget.catalog || {};
            const category = catalog.category ? catalog.category.replace(/_/g, ' ') : 'overview';
            return `
                <div class="col-lg-6">
                    <div class="content-card h-100">
                        <div class="content-card-body">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <span class="badge text-bg-light text-uppercase mb-2">${category}</span>
                                    <h2 class="h6 mb-1">${catalog.label || widget.key}</h2>
                                    <p class="text-muted small mb-0">${catalog.description || ''}</p>
                                </div>
                            </div>
                            <div class="home-dashboard-chart-wrap">
                                <canvas id="homeChart-${widget.key}" aria-label="${catalog.label || widget.key}"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        activeWidgets.forEach((widget) => {
            const canvas = document.getElementById(`homeChart-${widget.key}`);
            if (canvas) {
                renderChart(canvas, widget);
            }
        });
    };

    const load = async () => {
        const requestId = ++loadRequestId;
        root?.classList.add('home-dashboard-loading');

        try {
            const { data } = await api.get('/home/dashboard', { params: rangeParams() });

            if (requestId !== loadRequestId) {
                return;
            }

            availableWidgets = data.data?.available_widgets || [];
            activeWidgets = data.data?.widgets || [];
            currentRange = data.data?.date_range || currentRange;
            syncRangeControls();
            renderWidgets();
        } catch (error) {
            if (requestId !== loadRequestId) {
                return;
            }

            showAlert(getErrorMessage(error), 'danger');
        } finally {
            if (requestId === loadRequestId) {
                root?.classList.remove('home-dashboard-loading');
            }
        }
    };

    const applyRangeSelection = async () => {
        const preset = rangePresetEl?.value || 'this_month';

        if (preset === 'custom') {
            if (!fromDateEl?.value || !toDateEl?.value) {
                showAlert('Select both from and to dates for a custom range.', 'warning');
                return;
            }

            currentRange = resolveClientDateRange('custom', fromDateEl.value, toDateEl.value);
            customRangePending = false;
        } else {
            currentRange = resolveClientDateRange(preset);
            customRangePending = false;
        }

        customRangeWrap?.classList.toggle('d-none', preset !== 'custom');
        updateRangeSummary();
        await load();
    };
    rangePresetEl?.addEventListener('change', async () => {
        const preset = rangePresetEl.value;

        if (preset === 'custom') {
            showCustomRangePicker();
            return;
        }

        await applyRangeSelection();
    });

    applyRangeBtn?.addEventListener('click', applyRangeSelection);

    const openManageModal = () => {
        if (!widgetOptions) return;

        const selected = new Set(activeWidgets.map((widget) => widget.key));
        const grouped = availableWidgets.reduce((acc, widget) => {
            const category = widget.category || 'other';
            if (!acc[category]) {
                acc[category] = [];
            }
            acc[category].push(widget);
            return acc;
        }, {});

        widgetOptions.innerHTML = Object.entries(grouped).map(([category, widgets]) => `
            <div class="mb-3">
                <h6 class="text-uppercase text-muted small mb-2">${category.replace(/_/g, ' ')}</h6>
                ${widgets.map((widget) => `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="${widget.key}" id="widget-${widget.key}" ${selected.has(widget.key) ? 'checked' : ''}>
                        <label class="form-check-label" for="widget-${widget.key}">
                            <span class="fw-semibold">${widget.label}</span>
                            <span class="d-block small text-muted">${widget.description || ''}</span>
                        </label>
                    </div>
                `).join('')}
            </div>
        `).join('');

        widgetModal?.show();
    };

    manageBtn?.addEventListener('click', openManageModal);

    saveWidgetsBtn?.addEventListener('click', async () => {
        const widgets = Array.from(widgetOptions?.querySelectorAll('input[type="checkbox"]:checked') || [])
            .map((input) => input.value);

        if (!widgets.length) {
            showAlert('Select at least one widget.', 'warning');
            return;
        }

        saveWidgetsBtn.disabled = true;

        try {
            const { data } = await api.put('/home/dashboard/widgets', {
                widgets,
                ...rangeParams(),
            });
            activeWidgets = data.data?.widgets || [];
            widgetModal?.hide();
            renderWidgets();
            showAlert(data.message || 'Dashboard updated.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            saveWidgetsBtn.disabled = false;
        }
    });

    if (fromDateEl && !fromDateEl.value) {
        fromDateEl.value = monthStartDateInput();
    }

    if (toDateEl && !toDateEl.value) {
        toDateEl.value = todayDateInput();
    }

    syncRangeControls();
    await load();
});