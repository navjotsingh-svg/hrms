import axios from 'axios';

const TOKEN_KEY = 'api_token';

export const getToken = () => {
    const storedToken = localStorage.getItem(TOKEN_KEY);

    if (storedToken) {
        return storedToken;
    }

    const cookieMatch = document.cookie.match(/(?:^|; )api_token=([^;]*)/);

    if (!cookieMatch) {
        return null;
    }

    const cookieToken = decodeURIComponent(cookieMatch[1]);
    localStorage.setItem(TOKEN_KEY, cookieToken);

    return cookieToken;
};

export const setToken = (token) => {
    localStorage.setItem(TOKEN_KEY, token);
    document.cookie = `api_token=${encodeURIComponent(token)}; path=/; max-age=${60 * 60 * 24 * 30}; SameSite=Lax`;
};

export const clearToken = () => {
    localStorage.removeItem(TOKEN_KEY);
    document.cookie = 'api_token=; path=/; max-age=0; SameSite=Lax';
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export const establishWebSession = async (token = getToken()) => {
    if (!token) {
        throw new Error('Missing authentication token.');
    }

    await axios.post('/auth/session', {}, {
        withCredentials: true,
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
};

export const destroyWebSession = async () => {
    await axios.post('/auth/session/logout', {}, {
        withCredentials: true,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
};

const api = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const hasXsrfCookie = () => /(?:^|; )XSRF-TOKEN=/.test(document.cookie);

api.interceptors.request.use((config) => {
    const token = getToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    // Prefer the XSRF-TOKEN cookie (sent automatically via withXSRFToken and
    // refreshed by Laravel on every response). The meta tag token is rendered
    // once per page load and goes stale if the session regenerates, causing
    // CSRF mismatch errors on long-open pages.
    if (!hasXsrfCookie() && csrfToken()) {
        config.headers['X-CSRF-TOKEN'] = csrfToken();
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            window.location.reload();
        }

        return Promise.reject(error);
    },
);

export const getErrorMessage = (error, fallback = 'Something went wrong.') => {
    const data = error.response?.data;

    if (data instanceof Blob) {
        return fallback;
    }

    if (data?.errors) {
        const firstError = Object.values(data.errors)[0];

        return Array.isArray(firstError) ? firstError[0] : firstError;
    }

    if (data?.message) {
        return data.message;
    }

    if (error instanceof Error && error.message) {
        return error.message;
    }

    return fallback;
};

export default api;
