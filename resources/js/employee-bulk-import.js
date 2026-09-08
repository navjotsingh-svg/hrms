import api, { getErrorMessage } from './api';
import { aiExplainBulkImport } from './ai-tools';

const MAP_EXTRA = '__extra__';
const MAP_SKIP = '__skip__';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('bulkImportStepUpload');

    if (!root) {
        return;
    }

    const alertBox = document.getElementById('employeeBulkImportAlert');
    const stepUpload = document.getElementById('bulkImportStepUpload');
    const stepMapping = document.getElementById('bulkImportStepMapping');
    const stepResult = document.getElementById('bulkImportStepResult');
    const stepsNav = document.getElementById('bulkImportSteps');
    const fileInput = document.getElementById('bulkImportFileInput');
    const uploadBtn = document.getElementById('bulkImportUploadBtn');
    const mappingBody = document.getElementById('bulkImportMappingBody');
    const previewHead = document.getElementById('bulkImportPreviewHead');
    const previewBody = document.getElementById('bulkImportPreviewBody');
    const confirmBtn = document.getElementById('bulkImportConfirmBtn');
    const aiExplainWrap = document.getElementById('bulkImportAiExplainWrap');
    const aiExplainBtn = document.getElementById('bulkImportAiExplainBtn');
    const aiExplainResult = document.getElementById('bulkImportAiExplainResult');
    const backBtn = document.getElementById('bulkImportBackBtn');
    const cancelBtn = document.getElementById('bulkImportCancelBtn');
    const resultSummary = document.getElementById('bulkImportResultSummary');
    const resultFailed = document.getElementById('bulkImportResultFailed');
    const doneBtn = document.getElementById('bulkImportDoneBtn');

    let importId = null;
    let headers = [];
    let fields = [];
    let suggestedMapping = {};
    let previewRows = [];

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const hideAlert = () => alertBox?.classList.add('d-none');

    const setActiveStep = (step) => {
        stepsNav?.querySelectorAll('[data-step]').forEach((item) => {
            item.classList.toggle('bulk-import-step--active', item.dataset.step === step);
            item.classList.toggle('bulk-import-step--done', (
                (step === 'mapping' && item.dataset.step === 'upload')
                || (step === 'result' && (item.dataset.step === 'upload' || item.dataset.step === 'mapping'))
            ));
        });
    };

    const showStep = (step) => {
        stepUpload?.classList.toggle('d-none', step !== 'upload');
        stepMapping?.classList.toggle('d-none', step !== 'mapping');
        stepResult?.classList.toggle('d-none', step !== 'result');
        backBtn?.classList.toggle('d-none', step === 'upload');
        confirmBtn?.classList.toggle('d-none', step !== 'mapping');
        doneBtn?.classList.toggle('d-none', step !== 'result');
        uploadBtn?.classList.toggle('d-none', step !== 'upload');
        cancelBtn?.classList.toggle('d-none', step === 'result');
        setActiveStep(step);
    };

    const resetPage = () => {
        importId = null;
        headers = [];
        fields = [];
        suggestedMapping = {};
        previewRows = [];
        hideAlert();

        if (fileInput) {
            fileInput.value = '';
        }

        if (resultFailed) {
            resultFailed.innerHTML = '';
            resultFailed.classList.add('d-none');
        }

        aiExplainWrap?.classList.add('d-none');
        aiExplainResult?.classList.add('d-none');

        showStep('upload');
    };

    const buildFieldOptions = (selected = MAP_EXTRA) => {
        const groups = fields.reduce((acc, field) => {
            acc[field.group] = acc[field.group] || [];
            acc[field.group].push(field);
            return acc;
        }, {});

        let html = `<option value="${MAP_EXTRA}" ${selected === MAP_EXTRA ? 'selected' : ''}>Store as extra column</option>`;
        html += `<option value="${MAP_SKIP}" ${selected === MAP_SKIP ? 'selected' : ''}>Skip this column</option>`;

        Object.entries(groups).forEach(([group, groupFields]) => {
            html += `<optgroup label="${escapeHtml(group)}">`;
            groupFields.forEach((field) => {
                html += `<option value="${escapeHtml(field.key)}" ${selected === field.key ? 'selected' : ''}>${escapeHtml(field.label)}${field.required ? ' *' : ''}</option>`;
            });
            html += '</optgroup>';
        });

        return html;
    };

    const renderMappingStep = () => {
        if (!mappingBody) {
            return;
        }

        mappingBody.innerHTML = headers.map((header, index) => `
            <tr>
                <td><strong>${escapeHtml(header)}</strong></td>
                <td>
                    <select class="form-select form-select-sm bulk-import-map-select" data-column-index="${index}">
                        ${buildFieldOptions(suggestedMapping[header] || MAP_EXTRA)}
                    </select>
                </td>
            </tr>
        `).join('');

        if (previewHead && previewBody) {
            previewHead.innerHTML = `<tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr>`;
            previewBody.innerHTML = previewRows.map((row) => `
                <tr>${headers.map((header) => `<td>${escapeHtml(row[header] ?? '—')}</td>`).join('')}</tr>
            `).join('');
        }

        showStep('mapping');
    };

    const collectMapping = () => {
        const mapping = {};
        mappingBody?.querySelectorAll('[data-column-index]').forEach((select) => {
            const index = Number(select.dataset.columnIndex);
            const header = headers[index];

            if (header) {
                mapping[header] = select.value;
            }
        });

        return mapping;
    };

    const renderResult = (payload) => {
        const importData = payload.import || {};
        resultSummary.innerHTML = `
            <div class="alert alert-${importData.failed_count ? 'warning' : 'success'} mb-3">
                ${escapeHtml(importData.summary_message || 'Import completed.')}
            </div>
            <ul class="list-unstyled mb-0 small text-muted">
                <li>File: ${escapeHtml(importData.original_filename || '')}</li>
                <li>Total rows: ${importData.row_count ?? 0}</li>
                <li>Imported: ${importData.imported_count ?? 0}</li>
                <li>Failed: ${importData.failed_count ?? 0}</li>
            </ul>
        `;

        const failedRows = payload.failed_rows || [];

        if (failedRows.length) {
            const failedNote = importData.failed_count > failedRows.length
                ? `<p class="small text-muted mb-2">Showing first ${failedRows.length} failed rows.</p>`
                : '';

            resultFailed.innerHTML = `
                <h6 class="mt-4 mb-2">Failed rows</h6>
                ${failedNote}
                <div class="bulk-import-scroll-panel table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr><th style="width: 8rem;">Row</th><th>Error</th></tr>
                        </thead>
                        <tbody>
                            ${failedRows.map((row) => `
                                <tr>
                                    <td>${row.row_number}</td>
                                    <td class="text-danger">${escapeHtml(row.error_message || 'Unknown error')}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            resultFailed.classList.remove('d-none');
            aiExplainWrap?.classList.remove('d-none');
            aiExplainResult?.classList.add('d-none');
        } else {
            resultFailed.innerHTML = '';
            resultFailed.classList.add('d-none');
            aiExplainWrap?.classList.add('d-none');
        }

        showStep('result');
    };

    uploadBtn?.addEventListener('click', async () => {
        hideAlert();

        if (!fileInput?.files?.length) {
            showAlert('Choose an Excel or CSV file to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading…';

        try {
            const { data } = await api.post('/employees/bulk-import/upload', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const payload = data.data || {};
            importId = payload.import?.id;
            headers = payload.headers || [];
            fields = payload.fields || [];
            suggestedMapping = payload.suggested_mapping || {};
            previewRows = payload.preview_rows || [];

            if (!importId) {
                throw new Error('Upload did not return an import id.');
            }

            renderMappingStep();
        } catch (error) {
            showAlert(getErrorMessage(error, 'Unable to upload file.'));
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Upload & Map Columns';
        }
    });

    backBtn?.addEventListener('click', () => {
        resetPage();
    });

    confirmBtn?.addEventListener('click', async () => {
        hideAlert();

        if (!importId) {
            showAlert('Upload a file first.');
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Importing…';

        try {
            const { data } = await api.post(`/employees/bulk-import/${importId}/confirm`, {
                mapping: collectMapping(),
            });

            renderResult(data.data || {});

            if (data.message) {
                showAlert(data.message, 'success');
            }
        } catch (error) {
            showAlert(getErrorMessage(error, 'Import failed.'));
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm & Import';
        }
    });

    aiExplainBtn?.addEventListener('click', async () => {
        if (!importId) {
            showAlert('Import id not found.', 'danger');
            return;
        }

        aiExplainBtn.disabled = true;
        aiExplainBtn.textContent = 'AI working…';

        try {
            const result = await aiExplainBulkImport(importId);

            if (aiExplainResult) {
                aiExplainResult.textContent = result.explanation || 'No explanation generated.';
                aiExplainResult.classList.remove('d-none');
            }
        } catch (error) {
            showAlert(getErrorMessage(error, 'Could not explain import errors.'), 'danger');
        } finally {
            aiExplainBtn.disabled = false;
            aiExplainBtn.textContent = 'AI explain errors';
        }
    });
});
