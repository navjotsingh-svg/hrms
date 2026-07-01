import api, { getErrorMessage } from './api';
import { compressImageFiles } from './image-compress';
import { bindBackButton, buildCategoryReturnUrl, showAutoDismissAlert } from './form-utils';
import {
    bindRequestReviewHandlers,
    mountRequestShowActions,
} from './request-review';

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('leaveShowCard');
    const alertBox = document.getElementById('leaveShowAlert');
    const toolbarEl = document.getElementById('leaveShowCardToolbar');
    const detailsEl = document.getElementById('leaveShowCardDetails');
    const leaveId = card?.dataset.leaveId;

    if (!card || !leaveId) return;

    bindBackButton('leaveShowBackBtn', buildCategoryReturnUrl('leave'));

    const showAlert = (message, type = 'success') => {
        showAutoDismissAlert(alertBox, message, type);
    };

    const bindUploadForm = () => {
        const uploadForm = document.getElementById('leaveProofUploadForm');
        const uploadBtn = document.getElementById('leaveProofUploadBtn');

        uploadForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const proofInput = document.getElementById('leaveProofFiles');
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

                await api.post(`/leave-requests/${leaveId}/attachments`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                showAlert('Supporting documents uploaded successfully.');
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
            category: 'leave',
            entity_id: item.id,
            status: item.status,
            can_review: item.can_review,
            can_cancel: item.can_cancel,
            review_kind: 'leave',
            review_target: String(item.id),
        };

        const attachments = (item.attachments || []).map((file) => `
            <li><a href="${file.file_url}" target="_blank" rel="noopener">${file.original_name}</a></li>
        `).join('') || '<li class="text-muted">No attachments yet</li>';

        const proofAlert = item.proof_missing
            ? `<div class="col-12"><div class="alert alert-warning mb-0 py-2 small">Supporting documents are required before this leave can be approved. ${item.can_upload_proof ? 'Please upload proof below.' : item.can_bypass_proof ? 'As HR/Admin, you may approve this single-day request without proof using Approve above.' : 'Waiting for employee to upload proof.'}</div></div>`
            : '';

        const uploadSection = item.can_upload_proof ? `
            <div class="col-12">
                <form id="leaveProofUploadForm" class="border rounded p-3">
                    <div class="fw-semibold mb-2">Upload Supporting Documents</div>
                    <p class="small text-muted mb-3">You can add proof now if you did not have it when applying.</p>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <input type="file" class="form-control" id="leaveProofFiles" name="proofs[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100" id="leaveProofUploadBtn">Upload</button>
                        </div>
                    </div>
                </form>
            </div>
        ` : '';

        mountRequestShowActions(toolbarEl, actionItem);

        if (detailsEl) {
            detailsEl.innerHTML = `
            <div class="row g-4">
                ${proofAlert}
                <div class="col-md-6"><span class="text-muted">Employee</span><div class="fw-semibold">${item.employee?.full_name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Leave Type</span><div class="fw-semibold">${item.leave_type?.name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Dates</span><div>${item.dates_label || item.from_date_label || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Total Days</span><div>${item.total_days_label || item.total_days}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label}</div></div>
                <div class="col-md-6"><span class="text-muted">Applied On</span><div>${item.created_at_label || '—'}</div></div>
                ${item.reviewed_by?.name ? `<div class="col-md-6"><span class="text-muted">Approved By</span><div class="fw-semibold">${item.reviewed_by.name}</div></div>` : ''}
                ${item.reviewed_at_label ? `<div class="col-md-6"><span class="text-muted">Approved On</span><div>${item.reviewed_at_label}</div></div>` : ''}
                <div class="col-12"><span class="text-muted">Reason</span><div>${item.reason}</div></div>
                ${item.review_notes ? `<div class="col-12"><span class="text-muted">Review Notes</span><div>${item.review_notes}</div></div>` : ''}
                <div class="col-12"><span class="text-muted">Attachments</span><ul class="mb-0 ps-3">${attachments}</ul></div>
                ${uploadSection}
            </div>
        `;
        }

        bindUploadForm();
    };

    const load = async () => {
        try {
            const { data } = await api.get(`/leave-requests/${leaveId}`);
            render(data.data.leave_request);
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
        onError: (error) => {
            showAlert(getErrorMessage(error), 'danger');
        },
    });

    await load();
});
