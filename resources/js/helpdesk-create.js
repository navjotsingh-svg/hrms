import api, { getErrorMessage } from './api';
import { compressImageFiles } from './image-compress';
import { aiSuggestHelpdesk } from './ai-tools';

const routes = () => window.HRMS_WEB_ROUTES || {};

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('helpdeskCreateRoot');
    const form = document.getElementById('helpdeskCreateForm');
    const alertBox = document.getElementById('helpdeskCreateAlert');
    const categorySelect = document.getElementById('category');
    const prioritySelect = document.getElementById('priority');
    const attachmentsInput = document.getElementById('attachments');
    const submitBtn = document.getElementById('helpdeskCreateBtn');
    const aiSuggestBtn = document.getElementById('helpdeskAiSuggestBtn');
    const addCategoryBtn = document.getElementById('helpdeskAddCategoryBtn');
    const categoryForm = document.getElementById('helpdeskCategoryForm');
    const categoryNameInput = document.getElementById('helpdeskCategoryName');
    const canManage = root?.dataset.canManage === '1';
    let categoryModal = null;

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const fillSelect = (select, items, selectedValue = null) => {
        if (!select) return;
        select.innerHTML = '';
        (items || []).forEach((item) => {
            select.insertAdjacentHTML('beforeend', `<option value="${item.value}">${item.label}</option>`);
        });
        if (selectedValue) {
            select.value = String(selectedValue);
        }
    };

    const loadMeta = async (selectedCategoryId = null) => {
        const { data } = await api.get('/helpdesk-tickets/meta');
        fillSelect(categorySelect, data.data.categories, selectedCategoryId);
        fillSelect(prioritySelect, data.data.priorities, prioritySelect?.value || 'medium');
    };

    if (canManage && addCategoryBtn) {
        categoryModal = window.bootstrap?.Modal.getOrCreateInstance(document.getElementById('helpdeskCategoryModal'));

        addCategoryBtn.addEventListener('click', () => {
            categoryNameInput.value = '';
            categoryModal?.show();
        });

        categoryForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const saveBtn = document.getElementById('helpdeskCategorySaveBtn');
            saveBtn.disabled = true;

            try {
                const { data } = await api.post('/helpdesk-categories', {
                    name: categoryNameInput.value.trim(),
                });
                categoryModal?.hide();
                await loadMeta(data.data.category?.id);
                showAlert('Category created.');
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            } finally {
                saveBtn.disabled = false;
            }
        });
    }

    try {
        await loadMeta();
        if (!prioritySelect?.value) {
            prioritySelect.value = 'medium';
        }
    } catch (error) {
        showAlert(getErrorMessage(error), 'danger');
    }

    aiSuggestBtn?.addEventListener('click', async () => {
        const description = form.description.value.trim();

        if (description.length < 10) {
            showAlert('Enter at least a short note about your issue first.', 'warning');
            return;
        }

        aiSuggestBtn.disabled = true;
        aiSuggestBtn.textContent = 'AI working…';

        try {
            const suggestion = await aiSuggestHelpdesk(description);
            form.subject.value = suggestion.subject || form.subject.value;
            form.description.value = suggestion.description || description;

            if (suggestion.helpdesk_category_id) {
                form.category.value = String(suggestion.helpdesk_category_id);
            }

            if (suggestion.priority) {
                form.priority.value = suggestion.priority;
            }

            showAlert('Ticket draft suggested by AI. Review before submitting.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            aiSuggestBtn.disabled = false;
            aiSuggestBtn.textContent = 'AI suggest';
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitBtn.disabled = true;

        try {
            const files = Array.from(attachmentsInput?.files || []);
            submitBtn.textContent = files.length ? 'Compressing files...' : 'Submitting...';

            const preparedFiles = files.length ? await compressImageFiles(files) : [];
            const formData = new FormData();
            formData.append('subject', form.subject.value.trim());
            formData.append('description', form.description.value.trim());
            formData.append('helpdesk_category_id', form.category.value);
            formData.append('priority', form.priority.value || 'medium');
            preparedFiles.forEach((file) => formData.append('attachments[]', file));

            submitBtn.textContent = 'Submitting...';
            const { data } = await api.post('/helpdesk-tickets', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            const ticketId = data.data.ticket?.id;
            window.location.href = ticketId
                ? `${routes().helpdeskShow || '/helpdesk'}/${ticketId}`
                : (routes().helpdeskIndex || '/helpdesk');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Ticket';
        }
    });
});
