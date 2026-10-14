import api, { getErrorMessage } from './api';
import { initRichTextEditor, isEmptyEditorContent } from './rich-text-editor';

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('companyPolicyFormRoot');
    const form = document.getElementById('companyPolicyEditorForm');
    const alertBox = document.getElementById('companyPolicyFormAlert');
    const titleInput = document.getElementById('companyPolicyTitle');
    const categoryInput = document.getElementById('companyPolicyCategory');
    const descriptionInput = document.getElementById('companyPolicyDescription');
    const statusInput = document.getElementById('companyPolicyStatus');
    const requiresConsentInput = document.getElementById('companyPolicyRequiresConsent');
    const bodyTextarea = document.getElementById('companyPolicyBodyHtml');
    const saveBtn = document.getElementById('companyPolicySaveBtn');

    if (!root || !form) {
        return;
    }

    const mode = root.dataset.mode || 'create';
    const policyId = root.dataset.policyId || '';
    const visualTab = document.getElementById('companyPolicyVisualTab');
    const htmlTab = document.getElementById('companyPolicyHtmlTab');
    const visualPane = document.getElementById('companyPolicyVisualPane');
    let editor = null;
    let sourceMode = 'visual';

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const showAlert = (message, type = 'success') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const fillCategories = (categories) => {
        categoryInput.innerHTML = categories.map((item) => (
            `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
        )).join('');
    };

    const hasCustomHtml = (html) => /<style[\s>]/i.test(String(html || ''));

    const setSourceMode = (nextMode) => {
        if (nextMode === sourceMode) {
            return;
        }

        if (nextMode === 'visual' && hasCustomHtml(bodyTextarea.value)) {
            const proceed = window.confirm('The visual editor removes custom CSS. Switch anyway?');
            if (!proceed) {
                return;
            }
        }

        if (nextMode === 'html' && sourceMode === 'visual' && editor?.sync && !hasCustomHtml(bodyTextarea.value)) {
            editor.sync();
        }

        sourceMode = nextMode;
        const visual = nextMode === 'visual';
        visualPane?.classList.toggle('d-none', !visual);
        bodyTextarea.classList.toggle('d-none', visual);
        visualTab?.classList.toggle('active', visual);
        htmlTab?.classList.toggle('active', !visual);

        if (visual && editor?.quill) {
            editor.quill.root.innerHTML = bodyTextarea.value || '<p><br></p>';
        }
    };

    editor = initRichTextEditor({
        container: document.getElementById('companyPolicyBodyEditor'),
        textarea: bodyTextarea,
        placeholder: 'Write the full policy page content...',
        toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            [{ align: [] }],
            ['link'],
            ['clean'],
        ],
    });

    try {
        const { data } = await api.get('/company-policies/meta');
        fillCategories(data.data?.categories || []);

        if (mode === 'edit' && policyId) {
            const response = await api.get(`/company-policies/${policyId}`);
            const policy = response.data.data?.policy;
            if (!policy) {
                throw new Error('Policy not found.');
            }

            titleInput.value = policy.title || '';
            categoryInput.value = policy.category || '';
            descriptionInput.value = policy.description || '';
            statusInput.value = policy.status || 'draft';
            requiresConsentInput.checked = policy.requires_consent !== false;

            if (policy.body_html) {
                bodyTextarea.value = policy.body_html;
                if (hasCustomHtml(policy.body_html)) {
                    setSourceMode('html');
                } else if (editor?.quill) {
                    editor.quill.root.innerHTML = policy.body_html;
                }
            }
        }
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    visualTab?.addEventListener('click', () => setSourceMode('visual'));
    htmlTab?.addEventListener('click', () => setSourceMode('html'));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const bodyHtml = sourceMode === 'html'
            ? (bodyTextarea.value || '')
            : (editor?.sync?.() || bodyTextarea.value || '');

        if (!titleInput.value.trim() || !categoryInput.value) {
            showAlert('Title and category are required.', 'warning');
            return;
        }

        if (isEmptyEditorContent(bodyHtml) && statusInput.value === 'published') {
            showAlert('Add policy page content before publishing.', 'warning');
            return;
        }

        const payload = {
            title: titleInput.value.trim(),
            category: categoryInput.value,
            description: descriptionInput.value.trim() || null,
            body_html: isEmptyEditorContent(bodyHtml) ? '' : bodyHtml,
            status: statusInput.value,
            requires_consent: Boolean(requiresConsentInput.checked),
        };

        saveBtn.disabled = true;
        const original = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';

        try {
            if (mode === 'edit' && policyId) {
                await api.put(`/company-policies/${policyId}`, payload);
                showAlert('Policy updated successfully.');
            } else {
                const { data } = await api.post('/company-policies', payload);
                showAlert('Policy created successfully.');
                const id = data.data?.policy?.id;
                if (id) {
                    window.location.href = `/company-policies/${id}`;
                    return;
                }
            }

            window.location.href = '/company-policies';
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
            saveBtn.disabled = false;
            saveBtn.textContent = original;
        }
    });
});
