import axios from 'axios';
import apiClient from './apiClient'; // Переконайся, що цей файл існує поруч

// 1. Спеціальний клієнт ТІЛЬКИ для входу (без токенів, без інтерсепторів)
const rawApi = axios.create({
    baseURL: 'http://localhost/api', // Жорстко порт 80 (Docker)
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
    // withCredentials: true <-- ЦЕ МИ ПРИБРАЛИ, щоб не було помилки Network Error
});

// 2. Функція Логіну
export async function login(email, password) {
    // Відправляємо запит
    const { data } = await rawApi.post('/login', { email, password });

    // Зберігаємо токен
    localStorage.setItem('auth_token', data.token);

    return data.user;
}

// 3. Функція Виходу (яку вимагала помилка)
export async function logout() {
    try {
        // Тут використовуємо звичайний apiClient, бо треба передати токен
        await apiClient.post('/logout');
    } catch (e) {
        console.error('Logout error', e);
    } finally {
        localStorage.removeItem('auth_token');
    }
}

// 4. Функція отримання поточного юзера
export async function getCurrentUser() {
    const token = localStorage.getItem('auth_token');
    if (!token) return null;

    try {
        const { data } = await apiClient.get('/user');
        return data;
    } catch (e) {
        localStorage.removeItem('auth_token');
        return null;
    }
}