import { Modal } from 'bootstrap';
import api, { getErrorMessage } from './api';
import { bindEmployeeSearchSelect } from './employee-autocomplete';
import { composeActionGroup, renderEditIconButton, renderViewLink } from './action-icons';
import { initRichTextEditor, isEmptyEditorContent } from './rich-text-editor';

const statusClass = (status) => ({
    draft: 'company-status-pill--inactive',
    pending_signature: 'company-status-pill--warning',
    signed: 'company-status-pill--active',
    declined: 'company-status-pill--cancelled',
    cancelled: 'company-status-pill--cancelled',
}[status] || '');

const setEditorHtml = (editor, html) => {
    if (!editor?.quill) {
        return;
    }

    editor.quill.root.innerHTML = html || '';
    editor.sync?.();
};

const syncEditor = (editor) => editor?.sync?.();

document.addEventListener('DOMContentLoaded', async () => {
    const pageRoot = document.getElementById('docLettersPageRoot');
    if (!pageRoot) return;

    const canManage = pageRoot.dataset.canManage === '1';
    const showUrl = pageRoot.dataset.showUrl || '/documents-letters';
    const alertBox = document.getElementById('docLettersAlert');
    const pendingCard = document.getElementById('docLettersPendingCard');
    const pendingCount = document.getElementById('docLettersPendingCount');

    let meta = null;
    let templatesCache = [];
    let issueModal;
    let templateModal;
    let templateBodyEditor = null;
    let templateDescriptionEditor = null;
    let issueBodyEditor = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const fillSelect = (select, items, includeAll = true) => {
        if (!select) return;
        const current = select.value;
        select.innerHTML = includeAll ? '<option value="">All</option>' : '';
        (items || []).forEach((item) => {
            const value = item.value ?? item;
            const label = item.label ?? item;
            select.insertAdjacentHTML('beforeend', `<option value="${value}">${label}</option>`);
        });
        if (current && [...select.options].some((opt) => opt.value === current)) {
            select.value = current;
        }
    };

    const placeholderHelpHtml = (placeholders, target = 'template') => {
        if (!placeholders?.length) return '';
        const chips = placeholders.map((item) => (
            `<button type="button" class="btn btn-outline-secondary btn-sm doc-letter-placeholder-btn" data-placeholder-key="${item.key}" data-placeholder-target="${target}">{${item.key}}</button>`
        )).join(' ');
        return `Insert placeholders: ${chips}`;
    };

    const ensureTemplateEditors = () => {
        if (!templateBodyEditor) {
            templateBodyEditor = initRichTextEditor({
                container: document.getElementById('templateBodyEditor'),
                textarea: document.getElementById('templateBodyHtml'),
                placeholder: 'Write the letter content. Use placeholders for employee details.',
            });
        }

        if (!templateDescriptionEditor) {
            templateDescriptionEditor = initRichTextEditor({
                container: document.getElementById('templateDescriptionEditor'),
                textarea: document.getElementById('templateDescription'),
                placeholder: 'Optional internal description for HR',
                toolbar: [
                    ['bold', 'italic'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['clean'],
                ],
            });
        }
    };

    const ensureIssueBodyEditor = () => {
        if (!issueBodyEditor) {
            issueBodyEditor = initRichTextEditor({
                container: document.getElementById('issueBodyEditor'),
                textarea: document.getElementById('issueBodyHtml'),
                placeholder: 'Write the document content or choose a template above.',
            });
        }
    };

    const insertPlaceholder = (target, token) => {
        const editor = target === 'issue' ? issueBodyEditor : templateBodyEditor;
        if (!editor?.quill) return;

        const range = editor.quill.getSelection(true);
        editor.quill.insertText(range.index, token);
        editor.sync?.();
    };

    const setIssueBodyReadonly = (readonly) => {
        ensureIssueBodyEditor();
        issueBodyEditor?.quill?.enable(!readonly);
        document.getElementById('issueBodyEditor')?.classList.toggle('doc-letter-editor--readonly', readonly);
    };

    const loadMeta = async () => {
        if (!canManage) return;
        const { data } = await api.get('/document-letter-templates/meta');
        meta = data.data;
        fillSelect(document.getElementById('filterLetterCategory'), meta.categories || []);
        fillSelect(document.getElementById('filterTemplateCategory'), meta.categories || []);
        fillSelect(document.getElementById('issueCategory'), meta.categories || [], false);
        fillSelect(document.getElementById('templateCategory'), meta.categories || [], false);

        const statuses = Object.entries({
            draft: 'Draft',
            pending_signature: 'Pending Signature',
            signed: 'Signed',
            declined: 'Declined',
            cancelled: 'Cancelled',
        }).map(([value, label]) => ({ value, label }));
        fillSelect(document.getElementById('filterLetterStatus'), statuses);

        ['issuePlaceholderHelp', 'templatePlaceholderHelp'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = placeholderHelpHtml(
                meta.placeholders,
                id === 'issuePlaceholderHelp' ? 'issue' : 'template',
            );
        });
    };

    const loadSummary = async () => {
        try {
            const { data } = await api.get('/document-letters/summary');
            const count = data.data.pending_signature_count || 0;
            if (pendingCount) pendingCount.textContent = String(count);
            pendingCard?.classList.toggle('d-none', count === 0);
        } catch {
            pendingCard?.classList.add('d-none');
        }
    };

    const loadLetters = async (page = 1) => {
        const tableBody = document.getElementById('docLettersTableBody');
        const paginationInfo = document.getElementById('docLettersPaginationInfo');
        const paginationList = document.getElementById('docLettersPaginationList');
        const filterEmployeeId = document.getElementById('filterEmployeeId');

        const params = {
            page,
            per_page: 10,
            status: document.getElementById('filterLetterStatus')?.value || undefined,
            category: document.getElementById('filterLetterCategory')?.value || undefined,
            search: document.getElementById('filterLetterSearch')?.value?.trim() || undefined,
            employee_id: filterEmployeeId?.value || undefined,
        };

        const { data } = await api.get('/document-letters', { params });
        const letters = data.data.letters || [];
        const pagination = data.data.pagination;
        const cols = canManage ? 6 : 5;

        if (!letters.length) {
            tableBody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-5">No documents found.</td></tr>`;
        } else {
            tableBody.innerHTML = letters.map((item) => {
                const employeeCell = canManage
                    ? `<td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>`
                    : '';
                return `<tr>
                    <td><span class="fw-semibold">${item.document_number}</span><div class="small text-muted">${item.title}</div></td>
                    <td>${item.category_label}</td>
                    <td><span class="company-status-pill ${statusClass(item.status)}">${item.status_label}</span></td>
                    ${employeeCell}
                    <td>${item.issued_at_label || '—'}</td>
                    <td>${composeActionGroup({
                        view: renderViewLink(`${showUrl}/${item.id}`, 'View document'),
                    })}</td>
                </tr>`;
            }).join('');
        }

        if (paginationInfo && pagination) {
            paginationInfo.textContent = pagination.total
                ? `Showing ${pagination.from}–${pagination.to} of ${pagination.total}`
                : 'No records';
        }

        if (paginationList && pagination) {
            paginationList.innerHTML = '';
            for (let p = 1; p <= pagination.last_page; p += 1) {
                paginationList.insertAdjacentHTML('beforeend', `
                    <li class="page-item ${p === pagination.current_page ? 'active' : ''}">
                        <button type="button" class="page-link" data-page="${p}">${p}</button>
                    </li>
                `);
            }
            paginationList.querySelectorAll('[data-page]').forEach((btn) => {
                btn.addEventListener('click', () => loadLetters(Number(btn.dataset.page)).catch((e) => showAlert(getErrorMessage(e), 'danger')));
            });
        }
    };

    const loadTemplates = async (page = 1) => {
        if (!canManage) return;
        const tableBody = document.getElementById('docTemplatesTableBody');
        const paginationInfo = document.getElementById('docTemplatesPaginationInfo');
        const paginationList = document.getElementById('docTemplatesPaginationList');

        const params = {
            page,
            per_page: 10,
            category: document.getElementById('filterTemplateCategory')?.value || undefined,
            status: document.getElementById('filterTemplateStatus')?.value || undefined,
            search: document.getElementById('filterTemplateSearch')?.value?.trim() || undefined,
        };

        const { data } = await api.get('/document-letter-templates', { params });
        const templates = data.data.templates || [];
        templatesCache = templates;
        const pagination = data.data.pagination;

        if (!templates.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No templates found.</td></tr>';
        } else {
            tableBody.innerHTML = templates.map((item) => `<tr>
                <td><span class="fw-semibold">${item.name}</span>${item.is_default ? ' <span class="badge bg-light text-dark">Default</span>' : ''}</td>
                <td>${item.category_label}</td>
                <td>${item.requires_signature ? 'Required' : 'Not required'}</td>
                <td><span class="company-status-pill ${item.status === 'active' ? 'company-status-pill--active' : 'company-status-pill--inactive'}">${item.status === 'active' ? 'Active' : 'Inactive'}</span></td>
                <td>${item.updated_at_label || '—'}</td>
                <td>${composeActionGroup({
                    edit: renderEditIconButton('data-edit-template', item.id, 'Edit template'),
                })}</td>
            </tr>`).join('');

            tableBody.querySelectorAll('[data-edit-template]').forEach((btn) => {
                btn.addEventListener('click', () => openTemplateModal(Number(btn.dataset.editTemplate)));
            });
        }

        if (paginationInfo && pagination) {
            paginationInfo.textContent = pagination.total
                ? `Showing ${pagination.from}–${pagination.to} of ${pagination.total}`
                : 'No records';
        }

        if (paginationList && pagination) {
            paginationList.innerHTML = '';
            for (let p = 1; p <= pagination.last_page; p += 1) {
                paginationList.insertAdjacentHTML('beforeend', `
                    <li class="page-item ${p === pagination.current_page ? 'active' : ''}">
                        <button type="button" class="page-link" data-template-page="${p}">${p}</button>
                    </li>
                `);
            }
            paginationList.querySelectorAll('[data-template-page]').forEach((btn) => {
                btn.addEventListener('click', () => loadTemplates(Number(btn.dataset.templatePage)).catch((e) => showAlert(getErrorMessage(e), 'danger')));
            });
        }

        refreshIssueTemplateSelect();
    };

    const refreshIssueTemplateSelect = async () => {
        const select = document.getElementById('issueTemplateId');
        if (!select) return;
        try {
            const { data } = await api.get('/document-letter-templates', { params: { per_page: 50, status: 'active' } });
            const templates = data.data.templates || [];
            templatesCache = templates;
            const current = select.value;
            select.innerHTML = '<option value="">Custom content</option>';
            templates.forEach((item) => {
                select.insertAdjacentHTML('beforeend', `<option value="${item.id}">${item.name}</option>`);
            });
            if (current) select.value = current;
        } catch {
            /* ignore */
        }
    };

    const loadUploads = async () => {
        if (!canManage) return;
        const tableBody = document.getElementById('docUploadsTableBody');
        try {
            const { data } = await api.get('/employee-documents/pending');
            const documents = data.data.documents || [];
            if (!documents.length) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5">No pending uploads.</td></tr>';
                return;
            }
            tableBody.innerHTML = documents.map((item) => `<tr>
                <td>${item.employee?.full_name || '—'}<div class="small text-muted">${item.employee?.employee_code || ''}</div></td>
                <td>${item.document_type?.name || '—'}</td>
                <td>${item.original_name || '—'}</td>
                <td>${item.created_at ? new Date(item.created_at).toLocaleString() : '—'}</td>
                <td>
                    <a href="${window.HRMS_WEB_ROUTES?.employeeShow || '/employees'}/${item.employee_id}?tab=documents" class="btn btn-sm btn-outline-primary">Review</a>
                </td>
            </tr>`).join('');
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-5">${getErrorMessage(error)}</td></tr>`;
        }
    };

    const previewIssueDocument = async () => {
        const previewBox = document.getElementById('issuePreviewBox');
        const templateId = document.getElementById('issueTemplateId')?.value;
        const employeeId = document.getElementById('issueEmployeeId')?.value;
        syncEditor(issueBodyEditor);
        const bodyHtml = document.getElementById('issueBodyHtml')?.value || '';

        if (!previewBox) return;

        if (!templateId && !bodyHtml.trim()) {
            previewBox.innerHTML = '<span class="text-muted">Add body content or choose a template.</span>';
            return;
        }

        if (templateId) {
            try {
                const { data } = await api.post(`/document-letter-templates/${templateId}/preview`, {
                    employee_id: employeeId || undefined,
                    salary: document.getElementById('issueSalary')?.value || undefined,
                    joining_date: document.getElementById('issueJoiningDate')?.value || undefined,
                });
                previewBox.innerHTML = data.data.preview?.html || '';
                return;
            } catch (error) {
                previewBox.innerHTML = `<span class="text-danger">${getErrorMessage(error)}</span>`;
                return;
            }
        }

        previewBox.innerHTML = bodyHtml;
    };

    const applyTemplateToIssueForm = () => {
        const templateId = document.getElementById('issueTemplateId')?.value;
        const template = templatesCache.find((item) => String(item.id) === String(templateId));
        const categoryField = document.getElementById('issueCategory');
        const titleField = document.getElementById('issueTitle');
        const signatureField = document.getElementById('issueRequiresSignature');

        ensureIssueBodyEditor();

        if (!template) {
            setIssueBodyReadonly(false);
            return;
        }

        setEditorHtml(issueBodyEditor, template.body_html || '');
        setIssueBodyReadonly(true);
        if (categoryField) categoryField.value = template.category;
        if (titleField && !titleField.value) titleField.value = template.name;
        if (signatureField) signatureField.checked = !!template.requires_signature;
        previewIssueDocument();
    };

    const fetchTemplate = async (templateId) => {
        let template = templatesCache.find((item) => Number(item.id) === Number(templateId));

        if (template?.body_html) {
            return template;
        }

        try {
            const { data } = await api.get(`/document-letter-templates/${templateId}`);
            return data.data.template;
        } catch {
            return template || null;
        }
    };

    const openTemplateModal = async (templateId = null) => {
        ensureTemplateEditors();

        document.getElementById('templateEditId').value = templateId ? String(templateId) : '';
        document.getElementById('docLettersTemplateModalLabel').textContent = templateId ? 'Edit Template' : 'Create Template';
        document.getElementById('templateName').value = '';
        document.getElementById('templateSubject').value = '';
        document.getElementById('templateDescription').value = '';
        document.getElementById('templateBodyHtml').value = '';
        document.getElementById('templateCategory').value = meta?.categories?.[0]?.value || 'offer_letter';
        document.getElementById('templateStatus').value = 'active';
        document.getElementById('templateRequiresSignature').checked = true;
        document.getElementById('templateIsDefault').checked = false;

        if (templateId) {
            const template = await fetchTemplate(templateId);
            if (template) {
                document.getElementById('templateName').value = template.name;
                document.getElementById('templateSubject').value = template.subject || '';
                document.getElementById('templateDescription').value = template.description || '';
                document.getElementById('templateBodyHtml').value = template.body_html || '';
                document.getElementById('templateCategory').value = template.category;
                document.getElementById('templateStatus').value = template.status;
                document.getElementById('templateRequiresSignature').checked = !!template.requires_signature;
                document.getElementById('templateIsDefault').checked = !!template.is_default;
            }
        }

        setEditorHtml(templateDescriptionEditor, document.getElementById('templateDescription').value);
        setEditorHtml(templateBodyEditor, document.getElementById('templateBodyHtml').value);
        templateBodyEditor?.quill?.enable(true);

        templateModal?.show();
    };

    if (canManage) {
        const issueModalEl = document.getElementById('docLettersIssueModal');
        const templateModalEl = document.getElementById('docLettersTemplateModal');
        issueModal = issueModalEl ? Modal.getOrCreateInstance(issueModalEl) : null;
        templateModal = templateModalEl ? Modal.getOrCreateInstance(templateModalEl) : null;

        ensureTemplateEditors();
        ensureIssueBodyEditor();

        bindEmployeeSearchSelect({
            inputId: 'filterEmployeeSearch',
            hiddenId: 'filterEmployeeId',
            onSelect: () => loadLetters(1).catch((e) => showAlert(getErrorMessage(e), 'danger')),
            onClear: () => loadLetters(1).catch((e) => showAlert(getErrorMessage(e), 'danger')),
        });
        bindEmployeeSearchSelect({
            inputId: 'issueEmployeeSearch',
            hiddenId: 'issueEmployeeId',
            onSelect: () => previewIssueDocument(),
            onClear: () => previewIssueDocument(),
        });

        document.getElementById('docLettersPageRoot')?.addEventListener('click', (event) => {
            const placeholderBtn = event.target.closest('.doc-letter-placeholder-btn');
            if (!placeholderBtn) return;
            insertPlaceholder(
                placeholderBtn.dataset.placeholderTarget,
                `{${placeholderBtn.dataset.placeholderKey}}`,
            );
        });

        document.getElementById('docLettersIssueBtn')?.addEventListener('click', async () => {
            document.getElementById('docLettersIssueForm')?.reset();
            ensureIssueBodyEditor();
            setEditorHtml(issueBodyEditor, '');
            setIssueBodyReadonly(false);
            document.getElementById('issuePreviewBox').innerHTML = '<span class="text-muted">Select an employee and template to preview.</span>';
            await refreshIssueTemplateSelect();
            issueModal?.show();
        });

        document.getElementById('docLettersCreateTemplateBtn')?.addEventListener('click', () => {
            openTemplateModal().catch((e) => showAlert(getErrorMessage(e), 'danger'));
        });

        document.getElementById('issueTemplateId')?.addEventListener('change', applyTemplateToIssueForm);
        document.getElementById('issuePreviewBtn')?.addEventListener('click', () => {
            previewIssueDocument().catch((e) => showAlert(getErrorMessage(e), 'danger'));
        });

        document.querySelectorAll('[data-sample-template]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.sampleTemplate;
                const sample = meta?.sample_templates?.[key];
                if (!sample) return;
                ensureTemplateEditors();
                setEditorHtml(templateBodyEditor, sample);
                if (key === 'offer_letter') {
                    document.getElementById('templateCategory').value = 'offer_letter';
                    if (!document.getElementById('templateName').value) {
                        document.getElementById('templateName').value = 'Offer Letter';
                    }
                }
            });
        });

        document.getElementById('docLettersIssueForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncEditor(issueBodyEditor);
            const employeeId = document.getElementById('issueEmployeeId')?.value;
            const templateId = document.getElementById('issueTemplateId')?.value;
            const bodyHtml = document.getElementById('issueBodyHtml')?.value;

            if (!employeeId) {
                showAlert('Please select an employee.', 'danger');
                return;
            }

            if (!templateId && isEmptyEditorContent(bodyHtml)) {
                showAlert('Please choose a template or enter document body content.', 'danger');
                return;
            }

            try {
                const payload = {
                    employee_id: Number(employeeId),
                    title: document.getElementById('issueTitle').value.trim(),
                    category: document.getElementById('issueCategory').value,
                    salary: document.getElementById('issueSalary').value.trim() || undefined,
                    joining_date: document.getElementById('issueJoiningDate').value.trim() || undefined,
                    requires_signature: document.getElementById('issueRequiresSignature').checked,
                    issue_now: document.getElementById('issueNow').checked,
                };
                if (templateId) payload.template_id = Number(templateId);
                else payload.body_html = bodyHtml;

                await api.post('/document-letters', payload);
                issueModal?.hide();
                showAlert('Document issued successfully.');
                await loadLetters(1);
                await loadSummary();
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('docLettersTemplateForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncEditor(templateBodyEditor);
            syncEditor(templateDescriptionEditor);

            const templateId = document.getElementById('templateEditId').value;
            const bodyHtml = document.getElementById('templateBodyHtml').value;

            if (isEmptyEditorContent(bodyHtml)) {
                showAlert('Please enter the letter body content.', 'danger');
                return;
            }

            const payload = {
                name: document.getElementById('templateName').value.trim(),
                category: document.getElementById('templateCategory').value,
                subject: document.getElementById('templateSubject').value.trim() || null,
                description: document.getElementById('templateDescription').value.trim() || null,
                body_html: bodyHtml,
                requires_signature: document.getElementById('templateRequiresSignature').checked,
                is_default: document.getElementById('templateIsDefault').checked,
                status: document.getElementById('templateStatus').value,
            };

            try {
                if (templateId) {
                    await api.put(`/document-letter-templates/${templateId}`, payload);
                } else {
                    await api.post('/document-letter-templates', payload);
                }
                templateModal?.hide();
                showAlert('Template saved.');
                await loadTemplates(1);
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
        });

        document.getElementById('filterLetterReset')?.addEventListener('click', () => {
            document.getElementById('filterLetterStatus').value = '';
            document.getElementById('filterLetterCategory').value = '';
            document.getElementById('filterLetterSearch').value = '';
            document.getElementById('filterEmployeeId').value = '';
            document.getElementById('filterEmployeeSearch').value = '';
            loadLetters(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
        });

        ['filterLetterStatus', 'filterLetterCategory'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', () => {
                loadLetters(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
            });
        });

        document.getElementById('filterLetterSearch')?.addEventListener('input', () => {
            window.clearTimeout(window.docLettersSearchTimer);
            window.docLettersSearchTimer = window.setTimeout(() => {
                loadLetters(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
            }, 300);
        });

        document.getElementById('filterTemplateReset')?.addEventListener('click', () => {
            document.getElementById('filterTemplateCategory').value = '';
            document.getElementById('filterTemplateStatus').value = '';
            document.getElementById('filterTemplateSearch').value = '';
            loadTemplates(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
        });

        ['filterTemplateCategory', 'filterTemplateStatus'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', () => {
                loadTemplates(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
            });
        });

        document.getElementById('filterTemplateSearch')?.addEventListener('input', () => {
            window.clearTimeout(window.docTemplatesSearchTimer);
            window.docTemplatesSearchTimer = window.setTimeout(() => {
                loadTemplates(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
            }, 300);
        });

        document.getElementById('tab-templates')?.addEventListener('shown.bs.tab', () => {
            loadTemplates(1).catch((e) => showAlert(getErrorMessage(e), 'danger'));
        });

        document.getElementById('tab-uploads')?.addEventListener('shown.bs.tab', () => {
            loadUploads().catch((e) => showAlert(getErrorMessage(e), 'danger'));
        });
    }

    try {
        await loadMeta();
        await loadSummary();
        await loadLetters(1);
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }
});
