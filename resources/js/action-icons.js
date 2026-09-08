export const VIEW_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>';

export const EDIT_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325"/></svg>';

export const DELETE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>';

export const CANCEL_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/></svg>';

export const renderViewLink = (href, label = 'View') => `
    <a href="${href}" class="table-action-btn table-action-btn--view" data-save-return="1" title="${label}" aria-label="${label}">
        ${VIEW_ICON}
    </a>
`;

export const renderEditLink = (href, label = 'Edit') => `
    <a href="${href}" class="table-action-btn table-action-btn--edit" title="${label}" aria-label="${label}">
        ${EDIT_ICON}
    </a>
`;

export const renderViewIconButton = (attrName, id, label = 'View') => `
    <button type="button" class="table-action-btn table-action-btn--view" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${VIEW_ICON}
    </button>
`;

export const renderEditIconButton = (attrName, id, label = 'Edit') => `
    <button type="button" class="table-action-btn table-action-btn--edit" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${EDIT_ICON}
    </button>
`;

export const ADD_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/></svg>';

export const renderAddIconButton = (attrName, id, label = 'Add') => `
    <button type="button" class="table-action-btn table-action-btn--approve" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${ADD_ICON}
    </button>
`;

export const renderDeleteButton = (dataAttr, id, label = 'Delete', itemName = null) => `
    <button type="button" class="table-action-btn table-action-btn--delete" title="${label}" aria-label="${label}" ${dataAttr}="${id}"${itemName ? ` data-delete-name="${String(itemName).replace(/"/g, '&quot;')}"` : ''}>
        ${DELETE_ICON}
    </button>
`;

export const renderCancelButton = (id, label = 'Cancel', extraAttrs = '') => `
    <button type="button" class="table-action-btn table-action-btn--cancel" id="${id}" title="${label}" aria-label="${label}" ${extraAttrs}>
        ${CANCEL_ICON}
    </button>
`;

export const renderCancelIconButton = (attrName, id, label = 'Cancel request (withdraw)') => `
    <button type="button" class="table-action-btn table-action-btn--cancel" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${CANCEL_ICON}
    </button>
`;

const ACTION_BUTTON_ORDER = [
    'table-action-btn--cancel',
    'table-action-btn--view',
    'table-action-btn--approve',
    'table-action-btn--reject',
    'table-action-btn--edit',
    'table-action-btn--delete',
    'table-action-btn--mail',
    'table-action-btn--mark-paid',
];

export const renderActionPlaceholder = () => (
    '<span class="table-action-btn table-action-btn--placeholder" aria-hidden="true"></span>'
);

const actionButtonSortKey = (html) => {
    if (html.includes('table-action-btn--placeholder')) {
        return 0;
    }

    const index = ACTION_BUTTON_ORDER.findIndex((className) => html.includes(className));

    return index === -1 ? ACTION_BUTTON_ORDER.length : index;
};

export const sortActionButtons = (html = '') => {
    if (!html || !html.includes('table-action-btn')) {
        return html;
    }

    const buttons = html.match(/<(a|button|span)\b[^>]*table-action-btn[^>]*>[\s\S]*?<\/\1>/gi);

    if (!buttons || buttons.length <= 1) {
        return html;
    }

    return [...buttons]
        .sort((left, right) => actionButtonSortKey(left) - actionButtonSortKey(right))
        .join('');
};

export const renderActionGroup = (items) => `
    <div class="table-action-group">${sortActionButtons(items)}</div>
`;

export const composeActionGroup = (parts, { reserveCancelSlot = false } = {}) => {
    const cancel = parts.cancel
        || (reserveCancelSlot && parts.view ? renderActionPlaceholder() : '');

    const html = ['cancel', 'view', 'approve', 'reject', 'markPaid', 'edit', 'submit', 'add', 'delete']
        .map((key) => (key === 'cancel' ? cancel : (parts[key] || '')))
        .filter(Boolean)
        .join('');

    if (!html) {
        return '<span class="text-muted">—</span>';
    }

    return renderActionGroup(html);
};
