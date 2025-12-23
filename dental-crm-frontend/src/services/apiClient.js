import axios from 'axios';

const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost/api',
    timeout: 30000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

// Приватні допоміжні функції
function getSafeToken() {
    if (typeof window === 'undefined') return null;
    try {
        return localStorage.getItem('auth_token');
    } catch (error) {
        console.warn('LocalStorage error:', error);
        return null;
    }
}

function clearAuthTokenLocal() {
    if (typeof window === 'undefined') return;
    try {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        delete apiClient.defaults.headers.common['Authorization'];
    } catch (error) {
        console.warn('Token clear error:', error);
    }
}

// Інтерсептор запиту
apiClient.interceptors.request.use(
    (config) => {
        const token = getSafeToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        if (config.method === 'get') {
            config.params = {
                ...config.params,
                _t: Date.now()
            };
        }

        return config;
    },
    (error) => {
        console.error('Request error:', error);
        return Promise.reject(error);
    }
);

// Інтерсептор відповіді
apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        const { response, config } = error;

        if (!response) {
            console.error('Network error - no internet or server down');
            return Promise.reject(error);
        }

        const { status, data } = response;

        switch (status) {
            case 401:
                console.warn('Unauthorized - clearing token');
                clearAuthTokenLocal();
                // Автоматичний редирект на логін
                if (typeof window !== 'undefined') {
                    window.location.href = '/login';
                }
                break;
            case 403:
                console.warn('Forbidden:', config.url);
                break;
            case 404:
                console.warn('Not found:', config.url);
                break;
            case 422:
                console.warn('Validation error:', data?.errors || data?.message);
                break;
            default:
                console.error(`API Error ${status}:`, data?.message || 'Unknown error');
        }

        return Promise.reject(error);
    }
);

// Публічні функції для роботи з токенами
export function setAuthToken(token) {
    if (!token || typeof window === 'undefined') return;
    try {
        localStorage.setItem('auth_token', token);
        apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } catch (error) {
        console.error('Token save error:', error);
    }
}

export function clearAuthToken() {
    clearAuthTokenLocal();
}

export function getAuthToken() {
    return getSafeToken();
}

export default apiClient;
export { apiClient };