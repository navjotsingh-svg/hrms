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
    const savedCountEl = document.getElementById('homeDashboardSavedCount');
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
    let galleryTabs = [];
    let activeGalleryTab = 'leave';
    let loadRequestId = 0;
    let customRangePending = false;
    let addingWidgetKey = null;
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

    const chartJsType = (catalog = {}) => {
        if (catalog.chart_type === 'line') return 'line';
        if (catalog.chart_type === 'bar') return 'bar';
        return 'doughnut';
    };

    const renderChart = (canvas, widget) => {
        const catalog = widget.catalog || {};
        const data = widget.data || { labels: [], series: [] };
        const type = chartJsType(catalog);
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
                    backgroundColor: type === 'line' ? CHART_COLORS[0] : CHART_COLORS,
                    borderColor: CHART_COLORS[0],
                    borderWidth: type === 'line' ? 2 : 0,
                    tension: 0.35,
                    fill: false,
                    pointRadius: type === 'line' ? 4 : 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type === 'doughnut',
                        position: 'bottom',
                    },
                    title: {
                        display: Boolean(data.meta?.chart_title),
                        text: data.meta?.chart_title || '',
                        align: 'start',
                        font: { size: 13, weight: '600' },
                    },
                },
                scales: type === 'doughnut' ? {} : {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
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

    const savedWidgetKeys = () => new Set(activeWidgets.map((widget) => widget.key));

    const updateSavedCount = () => {
        if (savedCountEl) {
            savedCountEl.textContent = `Saved Charts (${activeWidgets.length})`;
        }
    };

    const previewClassForWidget = (widget) => {
        const label = (widget.chart_type_label || widget.chart_type || '').toLowerCase();
        if (label.includes('pie') || label.includes('donut') || label.includes('sunburst')) return 'pie';
        if (label.includes('line')) return 'line';
        if (label.includes('scatter')) return 'scatter';
        if (label.includes('heatmap')) return 'heatmap';
        if (label.includes('box')) return 'box';
        return 'bar';
    };

    const renderGalleryPreview = (widget) => {
        const previewType = previewClassForWidget(widget);
        return `<div class="home-dashboard-gallery-preview home-dashboard-gallery-preview--${previewType}" aria-hidden="true"></div>`;
    };

    const renderGalleryCard = (widget, savedKeys) => {
        const isSaved = savedKeys.has(widget.key);
        const isAdding = addingWidgetKey === widget.key;
        const typeLabel = widget.chart_type_label || (widget.chart_type === 'donut' ? 'Pie Chart' : 'Bar Chart');

        return `
            <div class="home-dashboard-gallery-card ${isSaved ? 'is-saved' : ''}">
                <span class="home-dashboard-chart-type-badge">${typeLabel}</span>
                ${renderGalleryPreview(widget)}
                <p class="home-dashboard-gallery-card-title">${widget.label}</p>
                <button
                    type="button"
                    class="btn btn-sm w-100 ${isSaved ? 'btn-outline-secondary' : 'btn-outline-primary'}"
                    data-add-widget="${widget.key}"
                    ${isSaved || isAdding ? 'disabled' : ''}
                >
                    ${isAdding ? 'Adding…' : (isSaved ? 'Added' : 'Add to Dashboard')}
                </button>
            </div>
        `;
    };

    const renderGalleryGrid = (widgets, savedKeys) => {
        if (!widgets.length) {
            return '<p class="text-muted small mb-0">No charts available in this tab for your role.</p>';
        }

        return `<div class="home-dashboard-gallery-grid">${widgets.map((widget) => renderGalleryCard(widget, savedKeys)).join('')}</div>`;
    };

    const renderGallery = () => {
        if (!widgetOptions) return;

        const savedKeys = savedWidgetKeys();
        const recommended = availableWidgets.filter((widget) => widget.recommended);
        const tabWidgets = availableWidgets.filter((widget) => widget.category === activeGalleryTab);
        const tabsHtml = galleryTabs.map((tab) => `
            <button
                type="button"
                class="home-dashboard-gallery-tab ${tab.key === activeGalleryTab ? 'is-active' : ''}"
                data-gallery-tab="${tab.key}"
            >${tab.label}</button>
        `).join('');

        widgetOptions.innerHTML = `
            <section class="home-dashboard-gallery-section mb-4">
                <button type="button" class="home-dashboard-gallery-section-toggle" data-gallery-section="recommended">
                    <span>Recommended Charts</span>
                    <span class="home-dashboard-gallery-chevron" aria-hidden="true"></span>
                </button>
                <div class="home-dashboard-gallery-section-body" id="homeDashboardRecommendedSection">
                    ${renderGalleryGrid(recommended, savedKeys)}
                </div>
            </section>

            <section class="home-dashboard-gallery-section">
                <button type="button" class="home-dashboard-gallery-section-toggle" data-gallery-section="all">
                    <span>All Charts</span>
                    <span class="home-dashboard-gallery-chevron" aria-hidden="true"></span>
                </button>
                <div class="home-dashboard-gallery-section-body" id="homeDashboardAllChartsSection">
                    <div class="home-dashboard-gallery-tabs" role="tablist">${tabsHtml}</div>
                    <div class="home-dashboard-gallery-tab-panel">
                        ${renderGalleryGrid(tabWidgets, savedKeys)}
                    </div>
                </div>
            </section>
        `;

        updateSavedCount();
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
            galleryTabs = data.data?.gallery_tabs || [];
            if (!galleryTabs.some((tab) => tab.key === activeGalleryTab)) {
                activeGalleryTab = galleryTabs[0]?.key || 'leave';
            }
            currentRange = data.data?.date_range || currentRange;
            syncRangeControls();
            renderWidgets();
            updateSavedCount();
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

    const addWidgetToDashboard = async (widgetKey) => {
        const keys = activeWidgets.map((widget) => widget.key);
        if (keys.includes(widgetKey)) {
            return;
        }

        addingWidgetKey = widgetKey;
        renderGallery();

        try {
            const { data } = await api.put('/home/dashboard/widgets', {
                widgets: [...keys, widgetKey],
                ...rangeParams(),
            });
            activeWidgets = data.data?.widgets || [];
            renderWidgets();
            renderGallery();
            showAlert('Chart added to dashboard.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            addingWidgetKey = null;
            renderGallery();
        }
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

    manageBtn?.addEventListener('click', () => {
        renderGallery();
        widgetModal?.show();
    });

    widgetOptions?.addEventListener('click', async (event) => {
        const tabBtn = event.target.closest('[data-gallery-tab]');
        if (tabBtn) {
            activeGalleryTab = tabBtn.dataset.galleryTab;
            renderGallery();
            return;
        }

        const addBtn = event.target.closest('[data-add-widget]');
        if (addBtn?.dataset.addWidget) {
            await addWidgetToDashboard(addBtn.dataset.addWidget);
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
