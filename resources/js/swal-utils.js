import Swal from 'sweetalert2';

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
