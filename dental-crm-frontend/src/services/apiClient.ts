import axios, { type AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'

interface RetryConfig {
  maxRetries: number
  retryDelay: number
  retryableStatuses: number[]
}

function shouldRetry(error: AxiosError, config: RetryConfig): boolean {
  if (!error.response) return true
  return config.retryableStatuses.includes(error.response.status)
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function addRetryInterceptor(
  axiosInstance: AxiosInstance,
  retryConfig: Partial<RetryConfig> = {}
): void {
  const config: RetryConfig = {
    maxRetries: 2,
    retryDelay: 1000,
    // 429 не ретраїмо, щоб не множити запити під час rate-limit
    retryableStatuses: [408, 500, 502, 503, 504],
    ...retryConfig
  }

  axiosInstance.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
      const originalRequest = error.config as AxiosRequestConfig & { _retryCount?: number }

      if (!originalRequest._retryCount) {
        originalRequest._retryCount = 0
      }

      if (
        originalRequest._retryCount < config.maxRetries &&
        shouldRetry(error, config)
      ) {
        originalRequest._retryCount++
        const delayMs =
          config.retryDelay * Math.pow(2, originalRequest._retryCount - 1)
        await delay(delayMs)
        return axiosInstance(originalRequest)
      }

      return Promise.reject(error)
    }
  )
}

const baseURL =
  // In dev we use Vite proxy `/api` -> backend to avoid CORS issues
  import.meta.env.DEV ? '/api' : import.meta.env.VITE_API_URL || '/api'

const apiClient = axios.create({
  baseURL,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  },
  // Додаємо withCredentials для CORS, якщо потрібно
  withCredentials: false
})

// Global cooldown window after 429 responses (prevents request spam while server throttles)
let rateLimitUntilMs = 0

// Додати retry логіку для мережевих помилок
addRetryInterceptor(apiClient)

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
    // If we recently got 429, do not send more requests until cooldown expires
    if (Date.now() < rateLimitUntilMs) {
      // Use a canceled error to avoid noisy error logs and extra UI errors
      return Promise.reject(new axios.CanceledError('Rate limit cooldown'))
    }

    const token = getSafeToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
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
    // Ignore cancelled requests (including our rate-limit cooldown)
    if (error?.code === 'ERR_CANCELED') {
      return Promise.reject(error)
    }

    const { response, config } = error

    // Network error
    if (!response) {
      const errorDetails = {
        message: error.message,
        code: error.code,
        name: error.name,
        stack: error.stack
      }
      
      if (error.config) {
        errorDetails.config = {
          url: error.config.url,
          method: error.config.method,
          baseURL: error.config.baseURL,
          fullURL: error.config.baseURL ? `${error.config.baseURL}${error.config.url}` : error.config.url,
          timeout: error.config.timeout,
          headers: error.config.headers,
          data: error.config.data
        }
      }
      
      if (error.request) {
        errorDetails.request = {
          readyState: error.request.readyState,
          status: error.request.status,
          statusText: error.request.statusText,
          responseURL: error.request.responseURL
        }
      }
      
      console.error('Network error - no internet or server down', errorDetails)
      return Promise.reject(error)
    }

    const { status, data } = response

    // Enrich conflict responses with human-readable details so UI can show "which conflicts and why"
    try {
      if ((status === 409 || status === 422) && data && (data.hard_conflicts || data.soft_conflicts)) {
        const formatConflictList = (conflicts: any): string => {
          if (!conflicts) return ''
          if (!Array.isArray(conflicts)) return ''

          // Series conflicts: [{ procedure_step_id, conflicts: [{code,message}, ...] }]
          if (conflicts.length > 0 && typeof conflicts[0] === 'object' && conflicts[0]?.conflicts) {
            return conflicts
              .map((entry: any) => {
                const step = entry.procedure_step_id ? `Етап ${entry.procedure_step_id}` : 'Етап'
                const inner = Array.isArray(entry.conflicts)
                  ? entry.conflicts
                      .map((c: any) => `- ${c?.message || c?.code || 'Конфлікт'}`)
                      .join('\n')
                  : ''
                return `${step}:\n${inner}`.trim()
              })
              .join('\n')
              .trim()
          }

          // Normal conflicts: [{code,message}, ...]
          return conflicts
            .map((c: any) => `- ${c?.message || c?.code || 'Конфлікт'}`)
            .join('\n')
            .trim()
        }

        const hardDetails = formatConflictList(data.hard_conflicts)
        const softDetails = formatConflictList(data.soft_conflicts)
        const details = [hardDetails, softDetails].filter(Boolean).join('\n').trim()

        if (details) {
          // Keep original message but add details for existing alert() usage across the app.
          // Avoid duplicating on retries or repeated interceptor passes.
          if (!data.conflicts_details) {
            const base = data.message || 'Конфлікти'
            data.message = `${base}\n${details}`
            data.conflicts_details = details
          }
        }
      }
    } catch (_e) {
      // never break error handling due to formatting
    }

    // Handle 429 Too Many Requests - set cooldown based on Retry-After header
    if (status === 429) {
      const retryAfterHeader = response.headers?.['retry-after']
      const retryAfterSeconds =
        Number(retryAfterHeader) || Number(data?.retry_after) || 3
      // Ensure a small minimum to stop bursts
      const cooldownMs = Math.max(1500, retryAfterSeconds * 1000)
      rateLimitUntilMs = Date.now() + cooldownMs
    }

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
export function setAuthToken(token: string) {
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
