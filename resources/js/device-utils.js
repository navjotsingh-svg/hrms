const STORAGE_KEY = 'hrms_device_mac';

const normalizeMac = (value) => {
    if (!value || typeof value !== 'string') {
        return null;
    }

    const cleaned = value.trim().toUpperCase().replace(/-/g, ':');
    const match = cleaned.match(/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/);

    return match ? cleaned : null;
};

const hashString = (value) => {
    let hash = 2166136261;

    for (let index = 0; index < value.length; index += 1) {
        hash ^= value.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }

    return (hash >>> 0).toString(16).padStart(8, '0');
};

const fingerprintToMac = (seed) => {
    const hex = `${hashString(seed)}${hashString(`${seed}:device`)}`.slice(0, 12).toUpperCase();

    return hex.match(/.{1,2}/g)?.join(':') ?? null;
};

const collectDeviceSignals = () => {
    const navigatorInfo = window.navigator || {};
    const screenInfo = window.screen || {};

    return [
        navigatorInfo.userAgent || '',
        navigatorInfo.platform || '',
        navigatorInfo.language || '',
        String(navigatorInfo.hardwareConcurrency || ''),
        String(navigatorInfo.maxTouchPoints || ''),
        String(screenInfo.width || ''),
        String(screenInfo.height || ''),
        String(screenInfo.colorDepth || ''),
        String(window.devicePixelRatio || ''),
        Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    ].join('|');
};

export const getDeviceMacAddress = async () => {
    try {
        const stored = normalizeMac(localStorage.getItem(STORAGE_KEY));

        if (stored) {
            return stored;
        }

        const generated = fingerprintToMac(collectDeviceSignals());

        if (generated) {
            localStorage.setItem(STORAGE_KEY, generated);
        }

        return generated;
    } catch {
        return null;
    }
};

export const formatMacAddress = (value) => normalizeMac(value) || value || '—';
