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
    let editor = null;

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

            if (editor?.quill && policy.body_html) {
                editor.quill.root.innerHTML = policy.body_html;
                bodyTextarea.value = policy.body_html;
            }
        }
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const bodyHtml = editor?.sync?.() || bodyTextarea.value || '';

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
