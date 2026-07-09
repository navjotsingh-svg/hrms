import api, { getErrorMessage } from './api';

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const formatReply = (text) => escapeHtml(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');

const initAssistantChat = () => {
    const widget = document.getElementById('assistantChatWidget');

    if (!widget) {
        return;
    }

    const fab = document.getElementById('assistantFab');
    const panel = document.getElementById('assistantPanel');
    const closeBtn = document.getElementById('assistantCloseBtn');
    const alertBox = document.getElementById('assistantAlert');
    const subtitle = document.getElementById('assistantWidgetSubtitle');
    const modeBadge = document.getElementById('assistantModeBadge');
    const messagesEl = document.getElementById('assistantMessages');
    const suggestionsEl = document.getElementById('assistantSuggestions');
    const form = document.getElementById('assistantChatForm');
    const input = document.getElementById('assistantMessageInput');
    const sendBtn = document.getElementById('assistantSendBtn');

    /** @type {Array<{role: 'user'|'assistant', content: string}>} */
    let history = [];
    let isSending = false;
    let isOpen = false;
    let metaLoaded = false;

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `assistant-panel-alert alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const hideAlert = () => {
        alertBox?.classList.add('d-none');
    };

    const scrollToBottom = () => {
        if (!messagesEl) {
            return;
        }

        messagesEl.scrollTop = messagesEl.scrollHeight;
    };

    const appendMessage = (role, content) => {
        if (!messagesEl) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = `assistant-message assistant-message--${role}`;
        wrapper.innerHTML = `<div class="assistant-message-bubble">${formatReply(content)}</div>`;
        messagesEl.appendChild(wrapper);
        scrollToBottom();
    };

    const renderSuggestions = (questions) => {
        if (!suggestionsEl || !questions?.length) {
            suggestionsEl?.classList.add('d-none');
            return;
        }

        suggestionsEl.classList.remove('d-none');
        suggestionsEl.innerHTML = `
            <div class="assistant-suggestions-label">Suggested questions</div>
            <div class="assistant-suggestions-list">
                ${questions.map((question) => `
                    <button type="button" class="btn btn-sm btn-outline-secondary assistant-suggestion-btn" data-question="${escapeHtml(question)}">
                        ${escapeHtml(question)}
                    </button>
                `).join('')}
            </div>
        `;
    };

    const setSending = (sending) => {
        isSending = sending;

        if (sendBtn) {
            sendBtn.disabled = sending;
            sendBtn.textContent = sending ? 'Sending…' : 'Send';
        }

        if (input) {
            input.disabled = sending;
        }
    };

    const setPanelOpen = (open) => {
        isOpen = open;
        panel?.classList.toggle('d-none', !open);
        fab?.setAttribute('aria-expanded', open ? 'true' : 'false');
        fab?.classList.toggle('assistant-fab--open', open);

        if (open) {
            hideAlert();
            input?.focus();
        }
    };

    const loadMeta = async () => {
        if (metaLoaded) {
            return;
        }

        try {
            const { data } = await api.get('/assistant/meta');
            const meta = data.data || {};

            if (subtitle && meta.employee_name) {
                const personaLabel = meta.persona ? meta.persona.toUpperCase() : 'EMPLOYEE';
                subtitle.textContent = `${personaLabel} mode · Hi ${meta.employee_name}, ask about leave, attendance, team insights, policies, and more.`;
            }

            if (modeBadge) {
                modeBadge.textContent = meta.ai_enabled ? 'AI enabled' : 'Smart answers';
                modeBadge.className = meta.ai_enabled
                    ? 'badge text-bg-primary'
                    : 'badge text-bg-light border';
            }

            renderSuggestions(meta.suggested_questions || []);
            metaLoaded = true;
        } catch (error) {
            showAlert(getErrorMessage(error));

            if (modeBadge) {
                modeBadge.textContent = 'Unavailable';
            }
        }
    };

    const sendMessage = async (message) => {
        const trimmed = message.trim();

        if (!trimmed || isSending) {
            return;
        }

        hideAlert();
        appendMessage('user', trimmed);

        if (input) {
            input.value = '';
        }

        setSending(true);

        try {
            const { data } = await api.post('/assistant/chat', {
                message: trimmed,
                history: history.slice(-12),
            });

            const reply = data.data?.reply || data.reply || 'Sorry, I could not generate a response.';

            appendMessage('assistant', reply);
            history.push({ role: 'user', content: trimmed });
            history.push({ role: 'assistant', content: reply });

            if (history.length > 12) {
                history = history.slice(-12);
            }
        } catch (error) {
            showAlert(getErrorMessage(error));
        } finally {
            setSending(false);
            input?.focus();
        }
    };

    const togglePanel = async () => {
        const nextOpen = !isOpen;
        setPanelOpen(nextOpen);

        if (nextOpen) {
            await loadMeta();
        }
    };

    fab?.addEventListener('click', () => {
        togglePanel();
    });

    closeBtn?.addEventListener('click', () => {
        setPanelOpen(false);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        await sendMessage(input?.value || '');
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form?.requestSubmit();
        }
    });

    suggestionsEl?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-question]');

        if (!button) {
            return;
        }

        sendMessage(button.dataset.question || '');
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            setPanelOpen(false);
        }
    });
};

document.addEventListener('DOMContentLoaded', initAssistantChat);

export default initAssistantChat;
