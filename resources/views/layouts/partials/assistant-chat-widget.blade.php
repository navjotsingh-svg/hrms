<div id="assistantChatWidget" class="assistant-widget" aria-live="polite">
    <div id="assistantPanel" class="assistant-panel d-none" role="dialog" aria-modal="true" aria-labelledby="assistantPanelTitle">
        <div class="assistant-panel-header">
            <div>
                <h2 class="assistant-panel-title" id="assistantPanelTitle">HR Assistant</h2>
                <p class="assistant-panel-subtitle mb-0" id="assistantWidgetSubtitle">Ask about your leave, attendance, and profile.</p>
            </div>
            <div class="assistant-panel-header-actions">
                <span class="badge text-bg-light border" id="assistantModeBadge">…</span>
                <button type="button" class="btn btn-sm btn-link assistant-panel-close" id="assistantCloseBtn" aria-label="Close chat">&times;</button>
            </div>
        </div>

        <div id="assistantAlert" class="assistant-panel-alert alert alert-danger d-none" role="alert"></div>

        <div class="assistant-chat-messages assistant-chat-messages--widget" id="assistantMessages">
            <div class="assistant-message assistant-message--assistant">
                <div class="assistant-message-bubble">
                    Hi! I can help with your leave balance, today's attendance, manager details, leave requests, holidays, and payslip availability.
                </div>
            </div>
        </div>

        <div class="assistant-suggestions border-top d-none" id="assistantSuggestions"></div>

        <div class="assistant-chat-composer border-top">
            <form id="assistantChatForm" class="assistant-chat-form">
                <label for="assistantMessageInput" class="visually-hidden">Message</label>
                <textarea
                    id="assistantMessageInput"
                    class="form-control assistant-chat-input"
                    rows="2"
                    maxlength="1000"
                    placeholder="Ask about your leave, attendance, manager…"
                ></textarea>
                <button type="submit" class="btn btn-primary assistant-chat-send" id="assistantSendBtn">Send</button>
            </form>
        </div>
    </div>

    <button type="button" class="assistant-fab" id="assistantFab" aria-label="Open HR Assistant" aria-expanded="false" aria-controls="assistantPanel">
        <svg class="assistant-fab-icon assistant-fab-icon--chat" xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M16 8c0 3.866-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.584.296-1.925.864-4.181 1.234-.2.032-.352-.176-.273-.362.354-.836.674-1.73.894-2.664-.614-.437-1-1.223-1-2.122 0-3.866 3.582-7 8-7s8 3.134 8 7M5 8a1 1 0 1 0-2 0 1 1 0 0 0 2 0m4 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
        </svg>
        <svg class="assistant-fab-icon assistant-fab-icon--close" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
        </svg>
    </button>
</div>
