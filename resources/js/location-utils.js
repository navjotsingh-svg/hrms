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

export function getCurrentPosition(options = {}) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Location is not supported on this device.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 15000,
            ...options,
        });
    });
}

/**
 * High-accuracy GPS often times out on desktops/laptops. Retry once with
 * relaxed settings (network-based location, accept a recent cached fix)
 * before giving up. Permission denials are not retried.
 */
export async function getPositionWithFallback() {
    try {
        return await getCurrentPosition();
    } catch (error) {
        if (error?.code === 1) {
            throw error;
        }

        return getCurrentPosition({
            enableHighAccuracy: false,
            timeout: 20000,
            maximumAge: 300000,
        });
    }
}

export function describeLocationError(error) {
    switch (error?.code) {
        case 1:
            return 'Location permission denied. Please allow location access for this site in your browser settings and try again.';
        case 2:
            return 'Location unavailable. Turn on device location (GPS) and try again.';
        case 3:
            return 'Could not fetch location in time. Check your connection/GPS and try again.';
        default:
            return null;
    }
}
