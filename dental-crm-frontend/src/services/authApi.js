// src/services/authApi.js
import apiClient from './apiClient';
import axios from 'axios';

// Окремий клієнт без інтерсепторів, щоб не було циклів
const rawApi = axios.create({
    // 👇 ВИПРАВЛЕННЯ: Беремо адресу з файлу .env (там у нас http://localhost)
    // Замість хардкоду http://127.0.0.1:8000/api
    baseURL: import.meta.env.VITE_API_URL + '/api',

    // 👇 ВАЖЛИВО: Додаємо це, щоб працювали CSRF-куки (якщо треба)
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
});

export async function login(email, password) {
    // Одразу робимо запит на логін
    const { data } = await rawApi.post('/login', { email, password });

    localStorage.setItem('auth_token', data.token);
    return data.user;
}

export async function logout() {
    try {
        await apiClient.post('/logout');
    } catch (e) {
        // якщо токен вже мертвий — ігноруємо
    }
    localStorage.removeItem('auth_token');
}

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