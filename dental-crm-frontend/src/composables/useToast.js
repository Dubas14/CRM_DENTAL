import { ref } from 'vue';

// Shared toast state across the app
const toasts = ref([]);

export function useToast() {
    const removeToast = (id) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };

    const showToast = (message, type = 'info', duration = 4000) => {
        const id = `${Date.now()}-${Math.random()}`;
        toasts.value.push({ id, message, type });

        if (duration) {
            setTimeout(() => removeToast(id), duration);
        }

        return id;
    };

    const success = (message, duration) => showToast(message, 'success', duration);
    const error = (message, duration) => showToast(message, 'error', duration);
    const warning = (message, duration) => showToast(message, 'warning', duration);
    const info = (message, duration) => showToast(message, 'info', duration);

    return {
        toasts,
        showToast,
        success,
        error,
        warning,
        info,
        removeToast,
    };
}