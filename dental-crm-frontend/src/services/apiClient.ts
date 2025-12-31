import axios from 'axios'
import { addRetryInterceptor } from '@/utils/retryHelper'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
})

// Додати retry логіку для мережевих помилок
addRetryInterceptor(apiClient, {
  maxRetries: 2,
  retryDelay: 1000,
  retryableStatuses: [408, 429, 500, 502, 503, 504]
})

// Приватні допоміжні функції
function getSafeToken() {
  if (typeof window === 'undefined') return null
  try {
    return localStorage.getItem('auth_token')
  } catch (error) {
    console.warn('LocalStorage error:', error)
    return null
  }
}

function clearAuthTokenLocal() {
  if (typeof window === 'undefined') return
  try {
    localStorage.removeItem('auth_token')
    localStorage.removeItem('user_data')
    delete apiClient.defaults.headers.common['Authorization']
  } catch (error) {
    console.warn('Token clear error:', error)
  }
}

// Інтерсептор запиту
apiClient.interceptors.request.use(
  (config) => {
    const token = getSafeToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    if (config.method === 'get') {
      config.params = {
        ...config.params,
        _t: Date.now()
      }
    }

    return config
  },
  (error) => {
    console.error('Request error:', error)
    return Promise.reject(error)
  }
)

// Інтерсептор відповіді
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { response, config } = error

    // Network error
    if (!response) {
      console.error('Network error - no internet or server down')
      return Promise.reject(error)
    }

    const { status, data } = response

    // Handle 401 Unauthorized
    if (status === 401) {
      console.warn('Unauthorized - clearing token')
      clearAuthTokenLocal()
      
      // Автоматичний редирект на логін (тільки якщо не на сторінці логіна)
      if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }

    // Log errors in development
    if (import.meta.env.DEV) {
      console.group(`API Error ${status}: ${config?.url}`)
      console.error('Status:', status)
      console.error('Message:', data?.message)
      if (data?.errors) {
        console.error('Validation Errors:', data.errors)
      }
      console.groupEnd()
    }

    return Promise.reject(error)
  }
)

// Публічні функції для роботи з токенами
export function setAuthToken(token) {
  if (!token || typeof window === 'undefined') return
  try {
    localStorage.setItem('auth_token', token)
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${token}`
  } catch (error) {
    console.error('Token save error:', error)
  }
}

export function clearAuthToken() {
  clearAuthTokenLocal()
}

export function getAuthToken() {
  return getSafeToken()
}

export default apiClient
export { apiClient }
