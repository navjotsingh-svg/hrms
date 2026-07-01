const escapeHtml = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

export const AVATAR_COLORS = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#14b8a6', '#6366f1', '#ef4444', '#22c55e'];

export const avatarColor = (seed = '', palette = AVATAR_COLORS) => {
    let hash = 0;

    for (let i = 0; i < seed.length; i += 1) {
        hash = seed.charCodeAt(i) + ((hash << 5) - hash);
    }

    return palette[Math.abs(hash) % palette.length];
};

export const getInitialsFromName = (name = '') => {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }

    return (parts[0]?.slice(0, 2) || 'EM').toUpperCase();
};

export const renderAvatarHtml = ({
    name = '',
    photoUrl = null,
    initials = null,
    className = '',
    style = null,
    seed = null,
    palette = AVATAR_COLORS,
} = {}) => {
    const displayInitials = initials || getInitialsFromName(name);
    const classes = [className];

    if (photoUrl) {
        classes.push('hrms-avatar--photo');

        return `<span class="${classes.filter(Boolean).join(' ')}"><img src="${escapeHtml(photoUrl)}" alt="" class="hrms-avatar-img"></span>`;
    }

    const background = style || `background:${avatarColor(seed || name, palette)}`;

    return `<span class="${classes.filter(Boolean).join(' ')}" style="${background}">${escapeHtml(displayInitials)}</span>`;
};
