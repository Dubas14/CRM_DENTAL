import { ref } from 'vue';

// Глобальний стан (щоб працювало з будь-якого компонента)
const toasts = ref([]);

export function useToast() {
    /**
     * Показати повідомлення
     * @param {string} message - Текст
     * @param {'success'|'error'|'info'} type - Тип (колір)
     */
    const addToast = (message, type = 'success') => {
        const id = Date.now() + Math.random();
        toasts.value.push({ id, message, type });

        // Автоматично видалити через 3 секунди
        setTimeout(() => removeToast(id), 3000);
    };

    const removeToast = (id) => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    };

    return { toasts, addToast, removeToast };
}