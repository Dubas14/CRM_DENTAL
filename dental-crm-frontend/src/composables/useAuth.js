import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from './useToast';
import authApi from '../services/authApi';

// Глобальні стани
const user = ref(null);
const loadingUser = ref(false);

export function useAuth() {
    const router = useRouter();
    const { showToast } = useToast();

    // Комп'ютед властивості
    const isLoggedIn = computed(() => !!user.value);

    // ПРОСТА функція логіну
    const login = async (email, password) => {
        try {
            loadingUser.value = true;
            const result = await authApi.login(email, password);

            user.value = result;

            showToast('Успішний вхід!', 'success');
            return result;

        } catch (error) {
            const message = error.message || 'Помилка входу';
            showToast(message, 'error');
            throw error;
        } finally {
            loadingUser.value = false;
        }
    };

    // ПРОСТА функція логауту
    const logout = async () => {
        try {
            await authApi.logout();
            user.value = null;

            showToast('Ви вийшли з системи', 'info');
            await router.push({ name: 'Login' });

        } catch (error) {
            console.warn('Logout error:', error);
            // Все одно очищаємо
            user.value = null;
            if (typeof window !== 'undefined') {
                localStorage.removeItem('auth_token');
            }
            await router.push({ name: 'Login' });
        }
    };

    // Отримання даних користувача
    const fetchUser = async () => {
        try {
            loadingUser.value = true;
            const userData = await authApi.getCurrentUser();
            user.value = userData;
            return userData;
        } catch (error) {
            console.error('Fetch user error:', error);
            user.value = null;
            throw error;
        } finally {
            loadingUser.value = false;
        }
    };

    // Відновлення сесії
    const restoreSession = async () => {
        if (typeof window === 'undefined') return;

        const token = localStorage.getItem('auth_token');
        if (token && !user.value) {
            try {
                await fetchUser();
            } catch (error) {
                console.warn('Cannot restore session:', error);
                // Очищаємо недійсний токен
                localStorage.removeItem('auth_token');
            }
        }
    };

    // Автоматичне відновлення при завантаженні
    onMounted(() => {
        restoreSession();
    });

    return {
        user,
        isLoggedIn,
        loadingUser,
        login,
        logout,
        fetchUser,
        restoreSession,
    };
}