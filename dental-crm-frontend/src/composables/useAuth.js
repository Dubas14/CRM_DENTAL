// src/composables/useAuth.js
import { ref, computed } from 'vue';
import { login as apiLogin, logout as apiLogout, getCurrentUser } from '../services/authApi';

const user = ref(null);
const loadingUser = ref(false);

export function useAuth() {
    const isLoggedIn = computed(() => !!user.value);

    const login = async (email, password) => {
        const u = await apiLogin(email, password);
        user.value = u;
        return u;
    };

    const logout = async () => {
        await apiLogout();
        user.value = null;
    };

    const fetchUser = async () => {
        loadingUser.value = true;
        try {
            const u = await getCurrentUser();
            user.value = u;
            return u;
        } finally {
            loadingUser.value = false;
        }
    };

    return { user, isLoggedIn, loadingUser, login, logout, fetchUser };
}
