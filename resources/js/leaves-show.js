import api, { getErrorMessage } from './api';
import { renderCancelButton } from './action-icons';
import { compressImageFiles } from './image-compress';
import { APPROVE_ICON, REJECT_ICON } from './review-actions';

document.addEventListener('DOMContentLoaded', async () => {
    const card = document.getElementById('leaveShowCard');
    const alertBox = document.getElementById('leaveShowAlert');
    const leaveId = card?.dataset.leaveId;

    if (!card || !leaveId) return;

    const showAlert = (message, type = 'success') => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
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
        const attachments = (item.attachments || []).map((file) => `
            <li><a href="${file.file_url}" target="_blank" rel="noopener">${file.original_name}</a></li>
        `).join('') || '<li class="text-muted">No attachments yet</li>';

        const proofAlert = item.proof_missing
            ? `<div class="col-12"><div class="alert alert-warning mb-0 py-2 small">Supporting documents are required before this leave can be approved. ${item.can_upload_proof ? 'Please upload proof below.' : 'Waiting for employee to upload proof.'}</div></div>`
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

        const actions = [];
        if (item.status === 'pending' && item.can_review) {
            actions.push(`<button type="button" class="table-action-btn table-action-btn--approve" id="approveLeaveBtn" title="Approve leave" aria-label="Approve leave">${APPROVE_ICON}</button>`);
            actions.push(`<button type="button" class="table-action-btn table-action-btn--reject" id="rejectLeaveBtn" title="Reject leave" aria-label="Reject leave">${REJECT_ICON}</button>`);
        }
        if (item.can_cancel) {
            actions.push(renderCancelButton('cancelLeaveBtn', 'Cancel leave'));
        }

        card.querySelector('.content-card-body').innerHTML = `
            <div class="row g-4">
                ${proofAlert}
                <div class="col-md-6"><span class="text-muted">Employee</span><div class="fw-semibold">${item.employee?.full_name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Leave Type</span><div class="fw-semibold">${item.leave_type?.name || '—'}</div></div>
                <div class="col-md-6"><span class="text-muted">Dates</span><div>${item.from_date_label} to ${item.to_date_label}</div></div>
                <div class="col-md-6"><span class="text-muted">Total Days</span><div>${item.total_days_label || item.total_days}</div></div>
                <div class="col-md-6"><span class="text-muted">Status</span><div class="fw-semibold text-capitalize">${item.status_label}</div></div>
                <div class="col-md-6"><span class="text-muted">Applied On</span><div>${item.created_at_label || '—'}</div></div>
                ${item.reviewed_by?.name ? `<div class="col-md-6"><span class="text-muted">Approved By</span><div class="fw-semibold">${item.reviewed_by.name}</div></div>` : ''}
                ${item.reviewed_at_label ? `<div class="col-md-6"><span class="text-muted">Approved On</span><div>${item.reviewed_at_label}</div></div>` : ''}
                <div class="col-12"><span class="text-muted">Reason</span><div>${item.reason}</div></div>
                ${item.review_notes ? `<div class="col-12"><span class="text-muted">Review Notes</span><div>${item.review_notes}</div></div>` : ''}
                <div class="col-12"><span class="text-muted">Attachments</span><ul class="mb-0 ps-3">${attachments}</ul></div>
                ${uploadSection}
                <div class="col-12"><div class="table-action-group">${actions.join('')}</div></div>
            </div>
        `;

        bindUploadForm();

        document.getElementById('approveLeaveBtn')?.addEventListener('click', async () => {
            try {
                await api.patch(`/leave-requests/${leaveId}/approve`);
                showAlert('Leave approved.');
                load();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('rejectLeaveBtn')?.addEventListener('click', async () => {
            const notes = prompt('Rejection reason:');
            if (!notes?.trim()) return;
            try {
                await api.patch(`/leave-requests/${leaveId}/reject`, { notes: notes.trim() });
                showAlert('Leave rejected.');
                load();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('cancelLeaveBtn')?.addEventListener('click', async () => {
            if (!confirm('Cancel this leave request?')) return;
            try {
                await api.patch(`/leave-requests/${leaveId}/cancel`);
                showAlert('Leave cancelled.');
                load();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });
    };

    const load = async () => {
        try {
            const { data } = await api.get(`/leave-requests/${leaveId}`);
            render(data.data.leave_request);
        } catch (error) {
            card.querySelector('.content-card-body').innerHTML = `<div class="text-danger py-4 text-center">${getErrorMessage(error)}</div>`;
        }
    };

    await load();
});
