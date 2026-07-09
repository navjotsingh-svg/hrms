import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { bindPagination, bindPerPageSelect, readPerPage, renderListPagination } from './pagination';
import { confirmAction } from './swal-utils';

const ALLOWED_ATTACHMENT_TYPES = new Set([
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
]);

const MAX_ATTACHMENTS = 5;
const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

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

const formatFileSize = (bytes) => {
    if (!bytes) return '0 B';

    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    if (bytes >= 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${bytes} B`;
};

const validateSelectedFiles = (files) => {
    if (files.length > MAX_ATTACHMENTS) {
        return `You can attach up to ${MAX_ATTACHMENTS} files.`;
    }

    for (const file of files) {
        if (!ALLOWED_ATTACHMENT_TYPES.has(file.type)) {
            return `"${file.name}" is not allowed. Use PDF or image files only.`;
        }

        if (file.size > MAX_ATTACHMENT_BYTES) {
            return `"${file.name}" is too large. Maximum size is 5 MB per file.`;
        }
    }

    return null;
};

const renderAttachments = (item) => {
    const attachments = item.attachments || [];

    if (!attachments.length) {
        return '';
    }

    return `
        <div class="moments-attachments mt-3">
            ${attachments.map((attachment) => {
                if (attachment.is_image) {
                    return `
                        <a href="${attachment.url}" target="_blank" rel="noopener noreferrer" class="moments-attachment-image-link">
                            <img src="${attachment.url}" alt="${escapeHtml(attachment.original_name)}" class="moments-attachment-image" loading="lazy">
                        </a>
                    `;
                }

                return `
                    <a href="${attachment.url}" target="_blank" rel="noopener noreferrer" class="moments-attachment-pdf">
                        <span aria-hidden="true">📄</span>
                        <span>${escapeHtml(attachment.original_name || 'PDF attachment')}</span>
                    </a>
                `;
            }).join('')}
        </div>
    `;
};

document.addEventListener('DOMContentLoaded', () => {
    const pageRoot = document.getElementById('praisePageRoot');
    const feed = document.getElementById('praiseFeed');
    if (!pageRoot || !feed) return;

    const canPost = pageRoot.dataset.canPost === '1';
    const canManage = pageRoot.dataset.canManage === '1';
    const praiseForm = document.getElementById('praiseForm');
    const praiseContent = document.getElementById('praiseContent');
    const praiseAttachments = document.getElementById('praiseAttachments');
    const praiseAttachmentPreview = document.getElementById('praiseAttachmentPreview');
    const praiseSubmitBtn = document.getElementById('praiseSubmitBtn');
    const paginationInfo = document.getElementById('praisePaginationInfo');
    const paginationList = document.getElementById('praisePaginationList');
    const perPageSelect = document.getElementById('praisePerPage');
    let currentPage = 1;
    let currentPerPage = readPerPage(perPageSelect);
    let selectedPraiseFiles = [];

    const showAlert = (message, type = 'success') => {
        const alertBox = document.getElementById('performanceAlert');
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const renderAttachmentPreview = () => {
        if (!praiseAttachmentPreview) return;

        if (!selectedPraiseFiles.length) {
            praiseAttachmentPreview.innerHTML = '';
            return;
        }

        praiseAttachmentPreview.innerHTML = selectedPraiseFiles.map((file) => `
            <span class="moments-attachment-preview-item">
                ${file.type.startsWith('image/') ? '🖼️' : '📄'}
                ${escapeHtml(file.name)} (${formatFileSize(file.size)})
            </span>
        `).join('');
    };

    const renderPraiseCard = (item) => {
        const celebrated = item.metadata?.employee_name || 'Colleague';
        const author = item.author?.name || 'Someone';
        const code = item.metadata?.employee_code ? `<span class="text-muted small ms-1">(${escapeHtml(item.metadata.employee_code)})</span>` : '';
        const contentHtml = item.content
            ? `<p class="mb-0">${escapeHtml(item.content)}</p>`
            : '';
        const deleteButton = canManage
            ? `<button type="button" class="btn btn-outline-danger btn-sm" data-praise-delete="${item.id}" title="Delete praise post">Delete</button>`
            : '';

        return `
            <article class="border rounded p-3 mb-3" data-praise-id="${item.id}">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <span class="badge text-bg-warning">Praise</span>
                        <span class="ms-2 fw-semibold">${escapeHtml(author)}</span>
                        <span class="text-muted"> recognized </span>
                        <span class="fw-semibold">${escapeHtml(celebrated)}</span>${code}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted">${formatTime(item.published_at)}</span>
                        ${deleteButton}
                    </div>
                </div>
                ${contentHtml}
                ${renderAttachments(item)}
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

    const deletePraise = async (momentId) => {
        const confirmed = await confirmAction({
            title: 'Delete praise post?',
            text: 'This recognition post will be permanently removed from the wall.',
            confirmText: 'Yes, delete',
        });

        if (!confirmed) {
            return;
        }

        try {
            await api.delete(`/performance/praise/${momentId}`);
            showAlert('Praise post deleted.');
            await loadPraise(currentPage);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    if (canPost) {
        bindEmployeeSearchSelect({
            inputId: 'praiseEmployeeSearch',
            hiddenId: 'praiseEmployeeId',
        });

        praiseAttachments?.addEventListener('change', () => {
            const files = Array.from(praiseAttachments.files || []);
            const error = validateSelectedFiles(files);

            if (error) {
                showAlert(error, 'warning');
                praiseAttachments.value = '';
                selectedPraiseFiles = [];
            } else {
                selectedPraiseFiles = files;
            }

            renderAttachmentPreview();
        });

        praiseForm?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const employeeId = document.getElementById('praiseEmployeeId')?.value;
            const content = praiseContent?.value?.trim() || '';
            const fileError = validateSelectedFiles(selectedPraiseFiles);

            if (!employeeId) {
                showAlert('Please select a colleague to recognize.', 'danger');
                return;
            }

            if (!content && !selectedPraiseFiles.length) {
                showAlert('Please write a message or attach at least one file.', 'danger');
                return;
            }

            if (fileError) {
                showAlert(fileError, 'warning');
                return;
            }

            praiseSubmitBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('employee_id', employeeId);
                formData.append('content', content);
                selectedPraiseFiles.forEach((file, index) => {
                    formData.append(`attachments[${index}]`, file);
                });

                await api.post('/performance/praise', formData);
                praiseForm.reset();
                selectedPraiseFiles = [];
                renderAttachmentPreview();
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

    feed.addEventListener('click', (event) => {
        const deleteButton = event.target.closest('[data-praise-delete]');

        if (!deleteButton) {
            return;
        }

        deletePraise(deleteButton.dataset.praiseDelete).catch((error) => {
            showAlert(getErrorMessage(error), 'danger');
        });
    });

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
