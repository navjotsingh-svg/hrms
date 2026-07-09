import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const formatTime = (iso) => {
    if (!iso) return '—';

    return new Date(iso).toLocaleString([], {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = document.getElementById('praisePageRoot');
    const feed = document.getElementById('praiseFeed');
    if (!pageRoot || !feed) return;

    const canPost = pageRoot.dataset.canPost === '1';
    const praiseForm = document.getElementById('praiseForm');
    const praiseContent = document.getElementById('praiseContent');
    const praiseSubmitBtn = document.getElementById('praiseSubmitBtn');
    const paginationInfo = document.getElementById('praisePaginationInfo');
    const paginationList = document.getElementById('praisePaginationList');
    const perPageSelect = document.getElementById('praisePerPage');
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);

    const showAlert = (message, type = 'success') => {
        const alertBox = document.getElementById('performanceAlert');
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderPraiseCard = (item) => {
        const celebrated = item.metadata?.employee_name || 'Colleague';
        const author = item.author?.name || 'Someone';
        const code = item.metadata?.employee_code ? `<span class="text-muted small ms-1">(${escapeHtml(item.metadata.employee_code)})</span>` : '';

        return `
            <article class="border rounded p-3 mb-3">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <span class="badge text-bg-warning">Praise</span>
                        <span class="ms-2 fw-semibold">${escapeHtml(author)}</span>
                        <span class="text-muted"> recognized </span>
                        <span class="fw-semibold">${escapeHtml(celebrated)}</span>${code}
                    </div>
                    <span class="small text-muted">${formatTime(item.published_at)}</span>
                </div>
                <p class="mb-0">${escapeHtml(item.content || '')}</p>
            </article>
        `;
    };

    const loadPraise = async (page = currentPage) => {
        currentPage = page;
        feed.innerHTML = '<div class="text-center text-muted py-5">Loading praise...</div>';

        try {
            const { data } = await api.get('/performance/praise', {
                params: { page, per_page: currentPerPage },
            });
            const moments = data.data.moments || [];
            const pagination = data.data.pagination;

            if (!moments.length) {
                feed.innerHTML = '<div class="text-center text-muted py-5">No praise shared yet. Be the first to recognize a colleague.</div>';
            } else {
                feed.innerHTML = moments.map(renderPraiseCard).join('');
            }

            renderListPagination({
                infoEl: paginationInfo,
                listEl: paginationList,
                perPageSelectEl: perPageSelect,
                pagination,
                itemLabel: 'praise posts',
                emptyMessage: 'No praise shared yet',
            });
        } catch (error) {
            feed.innerHTML = `<div class="text-center text-danger py-5">${escapeHtml(getErrorMessage(error))}</div>`;
        }
    };

    if (canPost) {
        bindEmployeeSearchSelect({
            inputId: 'praiseEmployeeSearch',
            hiddenId: 'praiseEmployeeId',
        });

        praiseForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const employeeId = document.getElementById('praiseEmployeeId')?.value;
            const content = praiseContent?.value?.trim() || '';

            if (!employeeId) {
                showAlert('Please select a colleague to recognize.', 'danger');
                return;
            }

            if (!content) {
                showAlert('Please write a praise message.', 'danger');
                return;
            }

            praiseSubmitBtn.disabled = true;

            try {
                await api.post('/performance/praise', {
                    employee_id: Number(employeeId),
                    content,
                });
                praiseForm.reset();
                document.getElementById('praiseEmployeeSearch')?.dispatchEvent(new Event('input', { bubbles: true }));
                showAlert('Praise shared successfully.');
                await loadPraise(1);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                praiseSubmitBtn.disabled = false;
            }
        });
    }

    document.getElementById('praiseRefreshBtn')?.addEventListener('click', () => {
        loadPraise(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    bindPagination(paginationList, (page) => {
        loadPraise(page).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    bindPerPageSelect(perPageSelect, (perPage) => {
        currentPerPage = perPage;
        loadPraise(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
    });

    loadPraise(1).catch((error) => showAlert(getErrorMessage(error), 'danger'));
});
