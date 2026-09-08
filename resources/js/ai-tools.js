import api, { getErrorMessage } from './api';

export const aiSuggestHelpdesk = async (description) => {
    const { data } = await api.post('/ai/helpdesk/suggest', { description });
    return data.data;
};

export const aiDraftDocument = async (payload) => {
    const { data } = await api.post('/ai/documents/draft', payload);
    return data.data;
};

export const aiGenerateJobDescription = async (payload) => {
    const { data } = await api.post('/ai/hiring/generate', payload);
    return data.data;
};

export const aiSuggestReview = async (payload) => {
    const { data } = await api.post('/ai/performance/review-suggest', payload);
    return data.data;
};

export const aiSuggestOneOnOne = async (payload) => {
    const { data } = await api.post('/ai/performance/one-on-one-suggest', payload);
    return data.data;
};

export const aiExplainBulkImport = async (importId) => {
    const { data } = await api.post(`/ai/bulk-import/${importId}/explain`);
    return data.data;
};

export const aiSummarizeAnalytics = async (reportKey, filters = {}) => {
    const { data } = await api.post('/ai/analytics/summarize', { report_key: reportKey, filters });
    return data.data;
};

export const aiAdviseRole = async (payload) => {
    const { data } = await api.post('/ai/roles/advise', payload);
    return data.data;
};

export const aiScanDataQuality = async () => {
    const { data } = await api.post('/ai/data-quality/scan');
    return data.data;
};

export const aiAskPolicy = async (question) => {
    const { data } = await api.post('/ai/policies/ask', { question });
    return data.data;
};

export const bindAiButton = (button, handler) => {
    if (!button) {
        return;
    }

    button.addEventListener('click', async () => {
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = 'AI working…';

        try {
            await handler();
        } catch (error) {
            window.alert(getErrorMessage(error));
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
};
