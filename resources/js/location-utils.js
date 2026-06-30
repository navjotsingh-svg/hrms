export async function reverseGeocode(latitude, longitude) {
    try {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=json&lat=${encodeURIComponent(latitude)}&lon=${encodeURIComponent(longitude)}&zoom=18&addressdetails=1`,
            {
                headers: {
                    'Accept-Language': 'en',
                },
            },
        );

        if (!response.ok) {
            return null;
        }

        const data = await response.json();
        const name = typeof data.display_name === 'string' ? data.display_name.trim() : '';

        return name || null;
    } catch {
        return null;
    }
}

export function formatCoordinates(latitude, longitude) {
    return `${Number(latitude).toFixed(5)}, ${Number(longitude).toFixed(5)}`;
}

export function isLikelyDesktopDevice() {
    const userAgent = navigator.userAgent || '';

    if (/Android|iPhone|iPad|iPod|Mobile/i.test(userAgent)) {
        return false;
    }

    return (navigator.maxTouchPoints ?? 0) <= 1;
}

export function requiresSecureContext() {
    return !window.isSecureContext;
}

export function getCurrentPosition(options = {}) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(createLocationError(0, 'Location is not supported in this browser.'));
            return;
        }

        if (requiresSecureContext()) {
            reject(createLocationError(0, 'Location requires a secure connection (HTTPS).'));
            return;
        }

        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0,
            ...options,
        });
    });
}

function createLocationError(code, message) {
    const error = new Error(message);
    error.code = code;

    return error;
}

/**
 * Desktop browsers (including Mac Safari/Chrome) often lack GPS. High-accuracy
 * requests time out first; try network/Wi‑Fi location before GPS on desktops.
 */
export async function getPositionWithFallback() {
    const attempts = isLikelyDesktopDevice()
        ? [
            { enableHighAccuracy: false, timeout: 30000, maximumAge: 300000 },
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 60000 },
            { enableHighAccuracy: false, timeout: 45000, maximumAge: 900000 },
        ]
        : [
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
            { enableHighAccuracy: false, timeout: 25000, maximumAge: 300000 },
        ];

    let lastError = null;

    for (const options of attempts) {
        try {
            return await getCurrentPosition(options);
        } catch (error) {
            lastError = error;

            if (error?.code === 1) {
                throw error;
            }
        }
    }

    throw lastError || createLocationError(2, 'Location unavailable.');
}

export function describeLocationError(error) {
    if (typeof error?.message === 'string' && error.message.includes('secure connection')) {
        return error.message;
    }

    switch (error?.code) {
        case 1:
            return isLikelyDesktopDevice()
                ? 'Location permission denied. On Mac: open System Settings → Privacy & Security → Location Services, turn it on, and allow your browser. Then reload this page and allow location when prompted.'
                : 'Location permission denied. Please allow location access for this site in your browser settings and try again.';
        case 2:
            return isLikelyDesktopDevice()
                ? 'Location unavailable. On Mac, enable Location Services and ensure your browser can use location (Wi‑Fi based).'
                : 'Location unavailable. Turn on device location (GPS) and try again.';
        case 3:
            return isLikelyDesktopDevice()
                ? 'Could not detect location in time. Check Wi‑Fi is connected, allow location for your browser, and try again.'
                : 'Could not fetch location in time. Check your connection/GPS and try again.';
        default:
            return null;
    }
}

export function describeCameraError(error) {
    if (requiresSecureContext()) {
        return 'Camera and location require a secure connection (HTTPS). Open the app using https:// instead of http://.';
    }

    const name = error?.name || '';

    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        return isLikelyDesktopDevice()
            ? 'Camera permission denied. On Mac: Safari → Settings for This Website → Camera → Allow. Also check System Settings → Privacy & Security → Camera and enable your browser.'
            : 'Camera permission denied. Allow camera access for this site in your browser settings and try again.';
    }

    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        return 'No camera was found. Connect a camera or use a device with a built-in camera.';
    }

    if (name === 'NotReadableError' || name === 'TrackStartError') {
        return 'Camera is in use by another app (FaceTime, Zoom, etc.). Close it and try again.';
    }

    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
        return 'This camera does not support the requested settings. Try again or use a different browser.';
    }

    if (name === 'AbortError') {
        return 'Camera access was interrupted. Please try again.';
    }

    return null;
}
