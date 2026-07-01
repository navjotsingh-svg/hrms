import api, { getErrorMessage } from './api';
import { compressImageFiles } from './image-compress';
import { bindBackButton, buildCategoryReturnUrl, showAutoDismissAlert } from './form-utils';
import { renderRequestAttachmentGallery } from './request-display';
import {
    bindRequestAttachmentHandlers,
    bindRequestAttachmentLightbox,
    loadAttachmentImagePreviews,
} from './request-attachments';
import {
    bindRequestReviewHandlers,
    mountRequestShowActions,
} from './request-review';

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('wfhShowCard');
    const alertBox = document.getElementById('wfhShowAlert');
    const toolbarEl = document.getElementById('wfhShowCardToolbar');
    const detailsEl = document.getElementById('wfhShowCardDetails');
    const wfhId = card?.dataset.wfhId;

    if (!card || !wfhId) return;

    bindBackButton('wfhShowBackBtn', buildCategoryReturnUrl('wfh'));

    const showAlert = (message, type = 'success') => {
        showAutoDismissAlert(alertBox, message, type);
    };

    bindRequestAttachmentLightbox();
    bindRequestAttachmentHandlers(detailsEl, {
        onError: (error) => showAlert(getErrorMessage(error), 'danger'),
    });

    const bindUploadForm = () => {
        const uploadForm = document.getElementById('wfhAttachmentUploadForm');
        const uploadBtn = document.getElementById('wfhAttachmentUploadBtn');

        uploadForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const proofInput = document.getElementById('wfhAttachmentFiles');
            const files = Array.from(proofInput?.files || []);

            if (!files.length) {
                showAlert('Select at least one file to upload.', 'warning');
                return;
            }

            uploadBtn.disabled = true;

            try {
                const preparedFiles = await compressImageFiles(files);
                const formData = new FormData();
                preparedFiles.forEach((file) => formData.append('proofs[]', file));

                await api.post(`/wfh-requests/${wfhId}/attachments`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                showAlert('Attachments uploaded successfully.');
                await load();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                uploadBtn.disabled = false;
            }
        });
    };

    const render = (item) => {
        const actionItem = {
            category: 'wfh',
            entity_id: item.id,
            status: item.status,
            can_review: item.can_review,
            can_cancel: item.can_cancel,
            review_kind: 'wfh',
            review_target: String(item.id),
        };

        const uploadSection = item.can_upload_proof ? `
            <div class="col-12">
                <form id="wfhAttachmentUploadForm" class="border rounded p-3">
                    <div class="fw-semibold mb-2">Upload Attachments</div>
                    <p class="small text-muted mb-3">Add supporting documents to this WFH request.</p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <input type="file" class="form-control" id="wfhAttachmentFiles" name="proofs[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100" id="wfhAttachmentUploadBtn">Upload</button>
                        </div>
                    </div>
                </form>
            </div>
        ` : '';

        mountRequestShowActions(toolbarEl, actionItem);

        if (detailsEl) {
            detailsEl.innerHTML = `
            <div class="row g-4">
                <div class="col-12">
                    <div class="alert alert-info mb-0 py-2 small">Approved WFH days allow punch in/out like a regular working day.</div>
                </div>
                <div class="col-md-6"><span class="text-muted">Employee</span><div class="fw-semibold">${item.employee?.full_name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Request Type</span><div class="fw-semibold">Work From Home</div></div>
                <div class="col-md-6"><span class="text-muted">Dates</span><div>${item.dates_label || item.from_date_label || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Total Days</span><div>${item.total_days_label || item.total_days}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label}</div></div>
                <div class="col-md-6"><span class="text-muted">Applied On</span><div>${item.created_at_label || '—'}</div></div>
                ${item.reviewed_by?.name ? `<div class="col-md-6"><span class="text-muted">Reviewed By</span><div class="fw-semibold">${item.reviewed_by.name}</div></div>` : ''}
                ${item.reviewed_at_label ? `<div class="col-md-6"><span class="text-muted">Reviewed On</span><div>${item.reviewed_at_label}</div></div>` : ''}
                <div class="col-12"><span class="text-muted">Reason</span><div>${item.reason || '—'}</div></div>
                ${item.review_notes ? `<div class="col-12"><span class="text-muted">Review Remarks</span><div>${item.review_notes}</div></div>` : ''}
                ${renderRequestAttachmentGallery(item.attachments || [])}
                ${uploadSection}
            </div>
        `;
        }

        bindUploadForm();
        loadAttachmentImagePreviews(detailsEl, {
            onError: (error) => showAlert(getErrorMessage(error), 'danger'),
        });
    };

    const load = async () => {
        try {
            const { data } = await api.get(`/wfh-requests/${wfhId}`);
            render(data.data.wfh_request);
        } catch (error) {
            if (toolbarEl) {
                toolbarEl.innerHTML = '';
                toolbarEl.classList.add('d-none');
            }

            if (detailsEl) {
                detailsEl.innerHTML = `<div class="text-danger py-4 text-center">${getErrorMessage(error)}</div>`;
            }
        }
    };

    bindRequestReviewHandlers(document, {
        onSuccess: async (message) => {
            showAlert(message);
            await load();
        },
        onError: (error) => showAlert(getErrorMessage(error), 'danger'),
    });

    await load();
});
