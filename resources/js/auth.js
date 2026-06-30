import api, { clearToken, establishWebSession, getToken, setToken } from './api';

const initLogin = async () => {
    const form = document.getElementById('loginForm');

    if (!form) {
        return;
    }

    const submitButton = document.getElementById('loginSubmitBtn');
    const alertBox = document.getElementById('loginAlert');

    const showAlert = (message, type = 'danger') => {
        if (!alertBox) {
            return;
        }

        alertBox.className = `alert alert-${type} py-2 small mb-4`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const redirectToDashboard = async (token) => {
        setToken(token);
        await establishWebSession(token);
        window.location.href = '/dashboard';
    };

    if (getToken()) {
        try {
            await api.get('/auth/me');
            await redirectToDashboard(getToken());
            return;
        } catch {
            clearToken();
        }
    }

    const handleLogin = async () => {
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Signing in...';
        }

        if (alertBox) {
            alertBox.classList.add('d-none');
        }

        try {
            const email = form.querySelector('#email')?.value?.trim();
            const password = form.querySelector('#password')?.value;

            const { data } = await api.post('/auth/login', {
                email,
                password,
                device_name: 'web-browser',
            });

            await redirectToDashboard(data.data.token);
        } catch (error) {
            showAlert(error.response?.data?.message || 'Invalid login credentials.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Sign In';
            }
        }
    };

    submitButton?.addEventListener('click', handleLogin);

    const passwordInput = form.querySelector('#password');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');

    togglePasswordBtn?.addEventListener('click', () => {
        if (!passwordInput) {
            return;
        }

        const showing = passwordInput.type === 'text';
        passwordInput.type = showing ? 'password' : 'text';
        togglePasswordBtn.textContent = showing ? 'Show' : 'Hide';
        togglePasswordBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        togglePasswordBtn.setAttribute('aria-pressed', showing ? 'false' : 'true');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        handleLogin();
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogin);
} else {
    initLogin();
}
