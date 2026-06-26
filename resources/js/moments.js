import api, { getErrorMessage } from './api';
import { applyMomentsUnreadBadges, markMomentsFeedSeen } from './moments-badges';

const REACTIONS = [
    { key: 'like', label: 'Like', emoji: '👍' },
    { key: 'love', label: 'Love', emoji: '❤️' },
    { key: 'insightful', label: 'Insightful', emoji: '💡' },
    { key: 'clap', label: 'Clap', emoji: '👏' },
    { key: 'note', label: 'Note', emoji: '📝' },
];

const COMMENT_EMOJIS = [
    '😀', '😃', '😄', '😁', '😊', '🙂', '😉', '😍', '🥳', '😎',
    '👍', '👏', '🙌', '🤝', '💪', '❤️', '💙', '💚', '🎉', '🎊',
    '✨', '🔥', '💯', '💡', '🙏', '👋', '🤗', '😂', '😅', '😢',
];

const CELEBRATION_TYPES = new Set(['birthday', 'work_anniversary', 'new_joinee']);

const TEMPLATE_FIELD_BY_TYPE = {
    birthday: 'birthday_template',
    work_anniversary: 'work_anniversary_template',
    new_joinee: 'new_joinee_template',
};

const TYPE_LABELS = {
    post: 'Post',
    birthday: 'Birthday',
    work_anniversary: 'Work Anniversary',
    new_joinee: 'New Joiner',
};

const TYPE_BADGE = {
    post: 'text-bg-primary',
    birthday: 'text-bg-warning',
    work_anniversary: 'text-bg-success',
    new_joinee: 'text-bg-info',
};

const ALLOWED_ATTACHMENT_TYPES = new Set([
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
]);

const MAX_ATTACHMENTS = 5;
const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const insertAtCursor = (textarea, text) => {
    if (!textarea) return;

    const start = textarea.selectionStart ?? textarea.value.length;
    const end = textarea.selectionEnd ?? textarea.value.length;
    const before = textarea.value.slice(0, start);
    const after = textarea.value.slice(end);

    textarea.value = `${before}${text}${after}`;
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
};

document.addEventListener('DOMContentLoaded', async () => {
    const pageRoot = document.getElementById('momentsPageRoot');
    const feed = document.getElementById('momentsFeed');
    const empty = document.getElementById('momentsEmpty');
    const alertBox = document.getElementById('momentsAlert');
    const pagination = document.getElementById('momentsPagination');
    const postForm = document.getElementById('momentsPostForm');
    const postType = document.getElementById('momentsPostType');
    const postEmployeeWrap = document.getElementById('momentsPostEmployeeWrap');
    const postEmployee = document.getElementById('momentsPostEmployee');
    const postTemplateHint = document.getElementById('momentsPostTemplateHint');
    const postContent = document.getElementById('momentsPostContent');
    const postAttachments = document.getElementById('momentsPostAttachments');
    const postAttachmentPreview = document.getElementById('momentsPostAttachmentPreview');
    const postBtn = document.getElementById('momentsPostBtn');
    const templatesCard = document.getElementById('momentsTemplatesCard');
    const templatesForm = document.getElementById('momentsTemplatesForm');
    const templatesToggle = document.getElementById('momentsTemplatesToggle');
    const refreshBtn = document.getElementById('momentsRefreshBtn');
    const filterButtons = Array.from(document.querySelectorAll('[data-moments-filter]'));
    const canComment = pageRoot?.dataset.canComment === '1';

    if (!feed) return;

    let currentFilter = '';
    let currentPage = 1;
    let selectedPostFiles = [];
    let momentEmployees = [];
    let momentTemplates = null;
    const momentsById = new Map();
    const loadedComments = new Map();
    const commentPanelOpen = new Map();

    const showAlert = (message, type = 'success') => {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show`;
        alertBox.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertBox.classList.remove('d-none');
    };

    const formatTime = (iso) => {
        if (!iso) return '—';
        return new Date(iso).toLocaleString([], {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatFileSize = (bytes) => {
        if (!bytes) return '';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    const getCommentsData = (momentId) => {
        if (loadedComments.has(momentId)) {
            return loadedComments.get(momentId);
        }

        const moment = momentsById.get(momentId);

        return {
            count: moment?.comments?.count || 0,
            items: moment?.comments?.items || [],
        };
    };

    const updatePostBtnState = () => {
        if (!postBtn) return;

        const type = postType?.value || 'post';
        const needsEmployee = CELEBRATION_TYPES.has(type);
        const hasEmployee = !needsEmployee || Boolean(postEmployee?.value);
        const hasContent = Boolean(postContent?.value.trim()) || selectedPostFiles.length > 0;

        postBtn.disabled = !hasEmployee || !hasContent;
    };

    const renderTemplateMessage = (type, employeeId) => {
        if (!momentTemplates || !employeeId || !CELEBRATION_TYPES.has(type)) {
            return '';
        }

        const employee = momentEmployees.find((item) => String(item.id) === String(employeeId));
        const templateKey = TEMPLATE_FIELD_BY_TYPE[type];
        const template = momentTemplates[templateKey] || '';

        if (!employee || !template) {
            return '';
        }

        return template
            .replaceAll('{name}', employee.full_name || '')
            .replaceAll('{employee_code}', employee.employee_code || '')
            .replaceAll('{years}', '1');
    };

    const handlePostTypeChange = () => {
        const type = postType?.value || 'post';
        const isCelebration = CELEBRATION_TYPES.has(type);

        postEmployeeWrap?.classList.toggle('d-none', !isCelebration);

        if (postTemplateHint) {
            postTemplateHint.textContent = isCelebration
                ? 'Pick a team member — the message field will pre-fill from your company template (you can edit it).'
                : '';
        }

        if (postContent) {
            postContent.placeholder = isCelebration
                ? 'Celebration message (optional — template used if left blank)'
                : 'Write something to celebrate or update the team...';
        }

        if (isCelebration && postEmployee?.value) {
            postContent.value = renderTemplateMessage(type, postEmployee.value);
        } else if (!isCelebration) {
            postContent.value = '';
        }

        updatePostBtnState();
    };

    const populateEmployeeSelect = () => {
        if (!postEmployee) return;

        postEmployee.innerHTML = '<option value="">Select employee</option>' + momentEmployees
            .map((employee) => `<option value="${employee.id}">${escapeHtml(employee.full_name)}${employee.employee_code ? ` (${escapeHtml(employee.employee_code)})` : ''}</option>`)
            .join('');
    };

    const loadSummary = async () => {
        try {
            const { data } = await api.get('/home/moments/summary');
            const summary = data.data || {};

            momentEmployees = summary.employees || [];
            momentTemplates = summary.templates || null;

            populateEmployeeSelect();

            if (summary.unread) {
                applyMomentsUnreadBadges(summary.unread);
            }

            if (templatesCard && summary.can_manage_templates) {
                templatesCard.classList.remove('d-none');

                if (momentTemplates) {
                    document.getElementById('momentsTemplateBirthday').value = momentTemplates.birthday_template || '';
                    document.getElementById('momentsTemplateAnniversary').value = momentTemplates.work_anniversary_template || '';
                    document.getElementById('momentsTemplateNewJoinee').value = momentTemplates.new_joinee_template || '';
                }
            }
        } catch (error) {
            console.error(getErrorMessage(error));
        }
    };

    const renderPostAttachmentPreview = () => {
        if (!postAttachmentPreview) return;

        if (!selectedPostFiles.length) {
            postAttachmentPreview.innerHTML = '';
            return;
        }

        postAttachmentPreview.innerHTML = selectedPostFiles.map((file) => `
            <span class="moments-attachment-preview-item">
                ${file.type.startsWith('image/') ? '🖼️' : '📄'}
                ${escapeHtml(file.name)} (${formatFileSize(file.size)})
            </span>
        `).join('');
    };

    const validateSelectedFiles = (files) => {
        if (files.length > MAX_ATTACHMENTS) {
            return `You can attach up to ${MAX_ATTACHMENTS} files.`;
        }

        for (const file of files) {
            if (!ALLOWED_ATTACHMENT_TYPES.has(file.type)) {
                return `"${file.name}" is not allowed. Use PDF or image files only.`;
            }

            if (file.size > MAX_ATTACHMENT_BYTES) {
                return `"${file.name}" is too large. Maximum size is 5 MB per file.`;
            }
        }

        return null;
    };

    const renderAuthor = (moment) => {
        if (moment.type !== 'post') {
            const metadata = moment.metadata || {};
            const celebratedName = metadata.employee_name || moment.author?.celebrated_name || moment.author?.name || 'Team Member';
            const isUserCelebration = moment.author?.type === 'user';

            return `
                <div class="moments-card-author">
                    <span class="moments-card-avatar moments-card-avatar--system">🎉</span>
                    <div>
                        <div class="fw-semibold">${escapeHtml(celebratedName)}</div>
                        <div class="small text-muted">${isUserCelebration
                            ? `Posted by ${escapeHtml(moment.author?.name || 'Team member')} · ${formatTime(moment.published_at)}`
                            : `Company celebration · ${formatTime(moment.published_at)}`}</div>
                    </div>
                </div>
            `;
        }

        const postCount = moment.author?.post_count || 0;
        const postCountLabel = postCount
            ? `${postCount} post${postCount === 1 ? '' : 's'}`
            : '';

        return `
            <div class="moments-card-author">
                <span class="moments-card-avatar">${escapeHtml((moment.author?.name || 'U').slice(0, 1).toUpperCase())}</span>
                <div>
                    <div class="fw-semibold">${escapeHtml(moment.author?.name || 'Team Member')}</div>
                    <div class="small text-muted">
                        ${formatTime(moment.published_at)}${postCountLabel ? ` · ${postCountLabel}` : ''}
                    </div>
                </div>
            </div>
        `;
    };

    const renderAuthorStats = (stats = []) => {
        const card = document.getElementById('momentsAuthorStatsCard');
        const container = document.getElementById('momentsAuthorStats');

        if (!container) {
            return;
        }

        if (!stats.length) {
            card?.classList.add('d-none');
            container.innerHTML = '';
            return;
        }

        card?.classList.remove('d-none');
        container.innerHTML = stats.map((entry) => `
            <div class="moments-author-stat">
                <span class="moments-author-stat-name">${escapeHtml(entry.name || 'Team Member')}</span>
                <span class="moments-author-stat-count">${entry.post_count} post${entry.post_count === 1 ? '' : 's'}</span>
            </div>
        `).join('');
    };

    const renderAttachments = (moment) => {
        const attachments = moment.attachments || [];

        if (!attachments.length) {
            return '';
        }

        return `
            <div class="moments-attachments mt-3">
                ${attachments.map((attachment) => {
                    if (attachment.is_image) {
                        return `
                            <a href="${attachment.url}" target="_blank" rel="noopener noreferrer" class="moments-attachment-image-link">
                                <img src="${attachment.url}" alt="${escapeHtml(attachment.original_name)}" class="moments-attachment-image" loading="lazy">
                            </a>
                        `;
                    }

                    return `
                        <a href="${attachment.url}" target="_blank" rel="noopener noreferrer" class="moments-attachment-pdf">
                            <span aria-hidden="true">📄</span>
                            <span>${escapeHtml(attachment.original_name || 'PDF attachment')}</span>
                        </a>
                    `;
                }).join('')}
            </div>
        `;
    };

    const renderReactions = (moment) => {
        const counts = moment.reactions?.counts || {};
        const viewerReaction = moment.reactions?.viewer_reaction;

        const buttons = REACTIONS.map((reaction) => {
            const count = counts[reaction.key] || 0;
            const active = viewerReaction === reaction.key ? ' moments-reaction-btn--active' : '';

            return `
                <button type="button" class="moments-reaction-btn${active}" data-moment-react="${moment.id}" data-reaction="${reaction.key}" title="${reaction.label}">
                    <span aria-hidden="true">${reaction.emoji}</span>
                    <span class="moments-reaction-count">${count || ''}</span>
                </button>
            `;
        }).join('');

        return `<div class="moments-reactions">${buttons}</div>`;
    };

    const renderCommentItem = (comment) => `
        <li class="moments-comment-item">
            <div class="moments-comment-author">${escapeHtml(comment.author?.name || 'Team Member')}</div>
            <div class="moments-comment-content">${escapeHtml(comment.content)}</div>
            <div class="moments-comment-time">${formatTime(comment.created_at)}</div>
        </li>
    `;

    const renderEmojiPicker = (momentId) => `
        <div class="moments-emoji-picker d-none" data-moment-emoji-picker="${momentId}">
            ${COMMENT_EMOJIS.map((emoji) => `
                <button type="button" class="moments-emoji-btn" data-moment-emoji="${momentId}" data-emoji="${emoji}" aria-label="Insert ${emoji}">${emoji}</button>
            `).join('')}
        </div>
    `;

    const renderComments = (momentId) => {
        const comments = getCommentsData(momentId);
        const count = comments.count || 0;
        const items = comments.items || [];
        const isExpanded = commentPanelOpen.has(momentId)
            ? commentPanelOpen.get(momentId)
            : (count > 0 || canComment);
        const hasHidden = count > items.length;

        return `
            <div class="moments-comments">
                <button
                    type="button"
                    class="moments-comments-toggle btn btn-link btn-sm px-0"
                    data-moment-toggle-comments="${momentId}"
                    aria-expanded="${isExpanded ? 'true' : 'false'}"
                >
                    ${count === 0 ? 'Comment' : `${count} comment${count === 1 ? '' : 's'}`}
                </button>
                <div class="moments-comments-panel ${isExpanded ? '' : 'd-none'}" data-moment-comments-panel="${momentId}">
                    ${items.length ? `<ul class="moments-comments-list">${items.slice().reverse().map(renderCommentItem).join('')}</ul>` : ''}
                    ${hasHidden ? `<button type="button" class="btn btn-link btn-sm px-0 mb-2" data-moment-load-comments="${momentId}">View all ${count} comments</button>` : ''}
                    ${canComment ? `
                        <form class="moments-comment-form" data-moment-comment-form="${momentId}">
                            <div class="moments-comment-compose">
                                ${renderEmojiPicker(momentId)}
                                <textarea class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Write a comment..." aria-label="Comment on moment"></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm moments-emoji-toggle" data-moment-emoji-toggle="${momentId}" title="Add emoji" aria-label="Add emoji">😊</button>
                                    <button type="submit" class="btn btn-primary btn-sm" disabled>Post Comment</button>
                                </div>
                            </div>
                        </form>
                    ` : ''}
                </div>
            </div>
        `;
    };

    const renderMoment = (moment) => `
        <article class="content-card moments-card mb-3" data-moment-id="${moment.id}">
            <div class="content-card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    ${renderAuthor(moment)}
                    <span class="badge ${TYPE_BADGE[moment.type] || 'text-bg-secondary'}">${TYPE_LABELS[moment.type] || moment.type}</span>
                </div>
                ${moment.content ? `<div class="moments-card-content">${escapeHtml(moment.content)}</div>` : ''}
                ${renderAttachments(moment)}
                ${moment.type === 'work_anniversary' && moment.metadata?.years ? `<div class="small text-muted mt-2">${moment.metadata.years} year(s) at the company</div>` : ''}
                ${renderReactions(moment)}
                ${renderComments(moment.id)}
            </div>
        </article>
    `;

    const refreshMomentComments = (momentId) => {
        const card = feed.querySelector(`[data-moment-id="${momentId}"]`);
        const commentsWrap = card?.querySelector('.moments-comments');

        if (commentsWrap) {
            commentsWrap.outerHTML = renderComments(momentId);
        }
    };

    const closeOtherEmojiPickers = (exceptMomentId = null) => {
        feed.querySelectorAll('[data-moment-emoji-picker]').forEach((picker) => {
            if (exceptMomentId && Number(picker.dataset.momentEmojiPicker) === exceptMomentId) {
                return;
            }

            picker.classList.add('d-none');
        });
    };

    const renderPagination = (meta) => {
        if (!pagination) return;

        if (!meta?.last_page || meta.last_page <= 1) {
            pagination.innerHTML = '';
            return;
        }

        pagination.innerHTML = Array.from({ length: meta.last_page }, (_, index) => {
            const page = index + 1;
            return `
                <li class="page-item ${page === meta.current_page ? 'active' : ''}">
                    <button type="button" class="page-link" data-page="${page}">${page}</button>
                </li>
            `;
        }).join('');
    };

    const load = async (page = currentPage) => {
        currentPage = page;
        momentsById.clear();
        loadedComments.clear();
        commentPanelOpen.clear();

        try {
            const params = { page, per_page: 10 };
            if (currentFilter) params.type = currentFilter;

            const { data } = await api.get('/home/moments', { params });
            const moments = data.data?.moments || [];

            moments.forEach((moment) => momentsById.set(moment.id, moment));

            if (!moments.length) {
                feed.innerHTML = '';
                empty?.classList.remove('d-none');
            } else {
                empty?.classList.add('d-none');
                feed.innerHTML = moments.map(renderMoment).join('');
            }

            renderPagination(data.data?.pagination);
            renderAuthorStats(data.data?.author_stats || []);

            if (data.data?.unread) {
                applyMomentsUnreadBadges(data.data.unread);
            }
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const loadAllComments = async (momentId) => {
        try {
            const { data } = await api.get(`/home/moments/${momentId}/comments`, { params: { per_page: 50 } });
            loadedComments.set(momentId, {
                count: data.data?.pagination?.total || data.data?.comments?.length || 0,
                items: data.data?.comments || [],
            });
            commentPanelOpen.set(momentId, true);
            refreshMomentComments(momentId);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        }
    };

    const submitComment = async (momentId, content, form) => {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        try {
            const { data } = await api.post(`/home/moments/${momentId}/comments`, { content });
            const comment = data.data?.comment;
            const existing = getCommentsData(momentId);
            loadedComments.set(momentId, {
                count: existing.count + 1,
                items: [comment, ...existing.items],
            });
            commentPanelOpen.set(momentId, true);
            refreshMomentComments(momentId);
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            submitBtn.disabled = !form.querySelector('textarea')?.value.trim();
        }
    };

    const resetPostForm = () => {
        if (postContent) postContent.value = '';
        if (postAttachments) postAttachments.value = '';
        if (postType) postType.value = 'post';
        if (postEmployee) postEmployee.value = '';
        selectedPostFiles = [];
        renderPostAttachmentPreview();
        handlePostTypeChange();
        updatePostBtnState();
    };

    postType?.addEventListener('change', handlePostTypeChange);

    postEmployee?.addEventListener('change', () => {
        const type = postType?.value || 'post';
        if (CELEBRATION_TYPES.has(type) && postEmployee?.value) {
            postContent.value = renderTemplateMessage(type, postEmployee.value);
        }
        updatePostBtnState();
    });

    postContent?.addEventListener('input', updatePostBtnState);

    postAttachments?.addEventListener('change', () => {
        const files = Array.from(postAttachments.files || []);
        const error = validateSelectedFiles(files);

        if (error) {
            showAlert(error, 'warning');
            postAttachments.value = '';
            selectedPostFiles = [];
        } else {
            selectedPostFiles = files;
        }

        renderPostAttachmentPreview();
        updatePostBtnState();
    });

    postForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const content = postContent?.value.trim() || '';
        const fileError = validateSelectedFiles(selectedPostFiles);

        if (!content && !selectedPostFiles.length) {
            return;
        }

        if (fileError) {
            showAlert(fileError, 'warning');
            return;
        }

        postBtn.disabled = true;

        try {
            const formData = new FormData();
            const type = postType?.value || 'post';

            formData.append('type', type);

            if (CELEBRATION_TYPES.has(type) && postEmployee?.value) {
                formData.append('employee_id', postEmployee.value);
            }

            if (content) {
                formData.append('content', content);
            }

            selectedPostFiles.forEach((file, index) => {
                formData.append(`attachments[${index}]`, file);
            });

            const { data } = await api.post('/home/moments', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            showAlert(data.message || 'Post shared.');
            resetPostForm();
            currentFilter = '';
            filterButtons.forEach((button) => button.classList.toggle('active', button.dataset.momentsFilter === ''));
            await load(1);
            await loadSummary();
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            updatePostBtnState();
        }
    });

    filterButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            currentFilter = button.dataset.momentsFilter || '';
            filterButtons.forEach((item) => item.classList.toggle('active', item === button));
            await load(1);
        });
    });

    feed.addEventListener('click', async (event) => {
        const reactBtn = event.target.closest('[data-moment-react]');
        if (reactBtn) {
            const momentId = reactBtn.dataset.momentReact;
            const reaction = reactBtn.dataset.reaction;
            const card = feed.querySelector(`[data-moment-id="${momentId}"]`);
            const current = card?.querySelector('.moments-reaction-btn--active')?.dataset.reaction;
            const nextReaction = current === reaction ? null : reaction;

            try {
                const { data } = await api.post(`/home/moments/${momentId}/react`, { reaction: nextReaction });
                if (data.data?.moment) {
                    momentsById.set(Number(momentId), data.data.moment);
                    const cardEl = feed.querySelector(`[data-moment-id="${momentId}"]`);
                    if (cardEl) {
                        cardEl.outerHTML = renderMoment(data.data.moment);
                    }
                }
            } catch (error) {
                showAlert(getErrorMessage(error), 'danger');
            }
            return;
        }

        const emojiToggle = event.target.closest('[data-moment-emoji-toggle]');
        if (emojiToggle) {
            const momentId = Number(emojiToggle.dataset.momentEmojiToggle);
            const picker = feed.querySelector(`[data-moment-emoji-picker="${momentId}"]`);
            if (picker) {
                const willOpen = picker.classList.contains('d-none');
                closeOtherEmojiPickers(momentId);
                picker.classList.toggle('d-none', !willOpen);
            }
            return;
        }

        const emojiBtn = event.target.closest('[data-moment-emoji]');
        if (emojiBtn) {
            const momentId = Number(emojiBtn.dataset.momentEmoji);
            const form = feed.querySelector(`[data-moment-comment-form="${momentId}"]`);
            const textarea = form?.querySelector('textarea');
            insertAtCursor(textarea, emojiBtn.dataset.emoji || '');
            return;
        }

        const toggleBtn = event.target.closest('[data-moment-toggle-comments]');
        if (toggleBtn) {
            const momentId = Number(toggleBtn.dataset.momentToggleComments);
            const comments = getCommentsData(momentId);
            const currentlyOpen = commentPanelOpen.has(momentId)
                ? commentPanelOpen.get(momentId)
                : (comments.count || 0) > 0;
            commentPanelOpen.set(momentId, !currentlyOpen);
            refreshMomentComments(momentId);
            return;
        }

        const loadBtn = event.target.closest('[data-moment-load-comments]');
        if (loadBtn) {
            await loadAllComments(Number(loadBtn.dataset.momentLoadComments));
        }
    });

    feed.addEventListener('input', (event) => {
        if (!(event.target instanceof HTMLTextAreaElement)) return;

        const form = event.target.closest('[data-moment-comment-form]');
        if (!form) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = !event.target.value.trim();
        }
    });

    feed.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-moment-comment-form]');
        if (!form) return;

        event.preventDefault();
        const momentId = Number(form.dataset.momentCommentForm);
        const content = form.querySelector('textarea')?.value.trim();
        if (!content) return;

        closeOtherEmojiPickers();
        await submitComment(momentId, content, form);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('.moments-emoji-toggle, .moments-emoji-picker, .moments-emoji-btn')) {
            return;
        }

        closeOtherEmojiPickers();
    });

    pagination?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-page]');
        if (!button) return;
        await load(Number(button.dataset.page));
    });

    refreshBtn?.addEventListener('click', async () => {
        await load(currentPage);
        await loadSummary();
    });

    templatesToggle?.addEventListener('click', () => {
        const expanded = templatesForm?.classList.toggle('d-none') === false;
        templatesToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        templatesToggle.textContent = expanded ? 'Hide templates' : 'Edit templates';
    });

    templatesForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const saveBtn = document.getElementById('momentsTemplatesSaveBtn');
        saveBtn.disabled = true;

        try {
            const payload = {
                birthday_template: document.getElementById('momentsTemplateBirthday')?.value.trim(),
                work_anniversary_template: document.getElementById('momentsTemplateAnniversary')?.value.trim(),
                new_joinee_template: document.getElementById('momentsTemplateNewJoinee')?.value.trim(),
            };

            const { data } = await api.put('/home/moments/templates', payload);
            momentTemplates = data.data?.templates || momentTemplates;
            showAlert(data.message || 'Templates saved.');
        } catch (error) {
            showAlert(getErrorMessage(error), 'danger');
        } finally {
            saveBtn.disabled = false;
        }
    });

    await loadSummary();
    await load(1);
    await markMomentsFeedSeen();
    handlePostTypeChange();
});
