// Окремий клієнт для логіну (без інтерсепторів)
const createLoginClient = () => {
  const baseURL = import.meta.env.VITE_API_URL || 'http://localhost/api'

  // Простий axios для логіну
  return {
    post: async (url, data) => {
      const response = await fetch(`${baseURL}${url}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      return {
        data: await response.json()
      }
    }
  }
}

const loginClient = createLoginClient()

// Функція логіну - ПРОСТА ТА ПРАЦЮЮЧА
export async function login(email, password) {
  try {
    const response = await loginClient.post('/login', { email, password })
    const { data } = response

    // Проста перевірка
    if (!data.token) {
      throw new Error('Токен не отримано від сервера')
    }

    // Просте збереження токена
    if (typeof window !== 'undefined') {
      localStorage.setItem('auth_token', data.token)
    }

    return data.user || data
  } catch (error) {
    console.error('Login error:', error)
    throw error
  }
}

// Функція логауту - ПРОСТА
export async function logout() {
  try {
    // Просте очищення токена
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user_data')
    }
  } catch (error) {
    console.warn('Logout error:', error)
  }
}

// Отримання поточного користувача - ПРОСТА
export async function getCurrentUser() {
  try {
    // Динамічний імпорт, щоб уникнути циклічних залежностей
    const { default: apiClient } = await import('./apiClient')
    const { data } = await apiClient.get('/user')
    return data
  } catch (error) {
    console.error('Get current user error:', error)
    throw error
  }
}

// Refresh token (якщо потрібно)
export async function refreshToken() {
  try {
    const { default: apiClient } = await import('./apiClient')
    const { data } = await apiClient.post('/auth/refresh')

    if (data.token && typeof window !== 'undefined') {
      localStorage.setItem('auth_token', data.token)
    }

    return data
  } catch (error) {
    console.error('Refresh token error:', error)
    throw error
  }
}

// Експорт за замовчуванням
export default {
  login,
  logout,
  getCurrentUser,
  refreshToken
}
