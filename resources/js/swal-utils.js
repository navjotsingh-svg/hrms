import Swal from 'sweetalert2';

const reviewRemarksHtml = (isApprove) => `
    <p class="request-review-swal-lead mb-3">
        ${isApprove
        ? 'You can add an optional remark that the employee will see with this approval.'
        : 'Please explain why this request is being rejected.'}
    </p>
    <label class="request-review-swal-label" for="swal-review-remarks">Remarks</label>
    <textarea
        id="swal-review-remarks"
        class="form-control request-review-swal-textarea"
        rows="4"
        maxlength="1000"
        placeholder="${isApprove ? 'Remarks (optional)' : 'Rejection reason (required)'}"
    ></textarea>
`;

export const promptRequestReviewRemarks = async ({ action, count = 1 } = {}) => {
    const isApprove = action === 'approve';
    const isBulk = count > 1;
    const title = isApprove
        ? (isBulk ? `Approve ${count} requests?` : 'Approve this request?')
        : (isBulk ? `Reject ${count} requests?` : 'Reject this request?');

    const result = await Swal.fire({
        title,
        html: reviewRemarksHtml(isApprove),
        icon: isApprove ? 'question' : 'warning',
        showCancelButton: true,
        confirmButtonText: isApprove ? 'Approve' : 'Reject',
        cancelButtonText: 'Close',
        confirmButtonColor: isApprove ? '#198754' : '#dc3545',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusConfirm: false,
        customClass: {
            popup: 'request-review-swal',
            htmlContainer: 'request-review-swal-body',
            confirmButton: 'request-review-swal-confirm',
            cancelButton: 'request-review-swal-cancel',
        },
        didOpen: () => {
            document.getElementById('swal-review-remarks')?.focus();
        },
        preConfirm: () => {
            const notes = document.getElementById('swal-review-remarks')?.value?.trim() || '';

            if (!isApprove && !notes) {
                Swal.showValidationMessage('Rejection reason is required.');
                return false;
            }

            return notes;
        },
    });

    if (!result.isConfirmed) {
        return null;
    }

    return result.value || '';
};

export const confirmAction = async ({
    title,
    text,
    html,
    confirmText = 'Yes',
    cancelText = 'No, keep it',
    icon = 'warning',
    confirmButtonColor = '#dc3545',
}) => {
    const result = await Swal.fire({
        title,
        text: html ? undefined : text,
        html,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor,
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: true,
    });

    return result.isConfirmed;
};

export const confirmLeaveTypeDelete = (name) => confirmAction({
    title: 'Delete leave type?',
    html: `<strong>${name}</strong> will be removed from the leave master.<br><span class="text-muted">Existing leave requests for this type will not be affected.</span>`,
    confirmText: 'Yes, delete',
});

export const confirmLeaveCancel = () => confirmAction({
    title: 'Cancel leave request?',
    text: 'This leave request will be cancelled and any reserved or used balance will be restored.',
    confirmText: 'Yes, cancel',
    confirmButtonColor: '#d97706',
});

export const confirmRequestCancel = () => confirmAction({
    title: 'Cancel request?',
    text: 'This request will be cancelled.',
    confirmText: 'Yes, cancel',
    confirmButtonColor: '#d97706',
});

export const showInfoAlert = ({
    title = 'Notice',
    text,
    icon = 'info',
    confirmText = 'OK',
} = {}) => Swal.fire({
    title,
    text,
    icon,
    confirmButtonText: confirmText,
    confirmButtonColor: '#2563eb',
});

export const showErrorAlert = ({
    title = 'Something went wrong',
    text,
} = {}) => Swal.fire({
    title,
    text,
    icon: 'error',
    confirmButtonText: 'OK',
    confirmButtonColor: '#dc3545',
});

export const showProfilePhotoPendingNotice = () => showInfoAlert({
    title: 'Profile photo submitted',
    text: 'Pending for approval from management.',
});
