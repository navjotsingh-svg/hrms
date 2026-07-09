import { VIEW_ICON } from './action-icons';

export const APPROVE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0"/></svg>';

export const REJECT_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/></svg>';

export const renderApproveIconButton = (attrName, id, label = 'Approve') => `
    <button type="button" class="table-action-btn table-action-btn--approve" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${APPROVE_ICON}
    </button>
`;

export const renderRejectIconButton = (attrName, id, label = 'Reject') => `
    <button type="button" class="table-action-btn table-action-btn--reject" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${REJECT_ICON}
    </button>
`;

export const MARK_PAID_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z"/></svg>';

export const renderMarkPaidIconButton = (attrName, id, label = 'Mark as paid') => `
    <button type="button" class="table-action-btn table-action-btn--approve" title="${label}" aria-label="${label}" ${attrName}="${id}">
        ${MARK_PAID_ICON}
    </button>
`;

export const renderViewDocumentIconButton = (id, title = 'Document', extraAttrs = '') => `
    <button type="button" class="table-action-btn table-action-btn--view" title="View" aria-label="View ${title}" data-view-document="${id}" data-view-title="${title}" ${extraAttrs}>
        ${VIEW_ICON}
    </button>
`;

export const DELETE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg>';

export const renderDeleteDocumentIconButton = (id, title = 'Document') => `
    <button type="button" class="table-action-btn table-action-btn--reject" title="Delete" aria-label="Delete ${title}" data-delete-document="${id}" data-delete-title="${title}">
        ${DELETE_ICON}
    </button>
`;

export const renderReviewIconActions = (approveAttr, rejectAttr, id) => `
    ${renderApproveIconButton(approveAttr, id)}
    ${renderRejectIconButton(rejectAttr, id)}
`;

export const renderReviewIconActionGroup = (approveAttr, rejectAttr, id) => `
    <div class="table-action-group">
        ${renderReviewIconActions(approveAttr, rejectAttr, id)}
    </div>
`;
