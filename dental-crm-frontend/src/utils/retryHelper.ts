import type { AxiosError, AxiosInstance, AxiosRequestConfig } from 'axios'

export interface RetryConfig {
  maxRetries: number
  retryDelay: number
  retryableStatuses: number[]
}

const defaultRetryConfig: RetryConfig = {
  maxRetries: 3,
  retryDelay: 1000,
  // 429 прибираємо, щоб не дратувати rate-limit додатковими ретраями
  retryableStatuses: [408, 500, 502, 503, 504]
}

/**
 * Перевірити чи помилка підлягає повторній спробі
 */
function shouldRetry(error: AxiosError, config: RetryConfig): boolean {
  // Мережеві помилки (timeout, no connection)
  if (!error.response) {
    return true
  }

  // Помилки за статус-кодом
  const status = error.response.status
  return config.retryableStatuses.includes(status)
}

/**
 * Затримка перед повторною спробою
 */
function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

/**
 * Додати retry логіку до Axios instance
 */
export function addRetryInterceptor(
  axiosInstance: AxiosInstance,
  retryConfig: Partial<RetryConfig> = {}
): void {
  const config: RetryConfig = { ...defaultRetryConfig, ...retryConfig }

  axiosInstance.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
      const originalRequest = error.config as AxiosRequestConfig & { _retryCount?: number }

      // Ініціалізувати лічильник спроб
      if (!originalRequest._retryCount) {
        originalRequest._retryCount = 0
      }

      // Перевірити чи можна повторити запит
      if (originalRequest._retryCount < config.maxRetries && shouldRetry(error, config)) {
        originalRequest._retryCount++

        // Експоненціальна затримка: 1s, 2s, 4s...
        const delayMs = config.retryDelay * Math.pow(2, originalRequest._retryCount - 1)

        console.warn(
          `Retry attempt ${originalRequest._retryCount}/${config.maxRetries} for ${originalRequest.url} after ${delayMs}ms`
        )

        await delay(delayMs)

        // Повторити запит
        return axiosInstance(originalRequest)
      }

      // Більше спроб немає або помилка не підлягає retry
      return Promise.reject(error)
    }
  )
}

/**
 * Wrapper функція для ручного retry запитів
 */
export async function retryRequest<T>(
  requestFn: () => Promise<T>,
  retryConfig: Partial<RetryConfig> = {}
): Promise<T> {
  const config: RetryConfig = { ...defaultRetryConfig, ...retryConfig }
  let lastError: Error | null = null

  for (let attempt = 0; attempt <= config.maxRetries; attempt++) {
    try {
      return await requestFn()
    } catch (error) {
      lastError = error as Error

      // Якщо це не AxiosError або не підлягає retry - кидаємо помилку
      if (!(error as AxiosError).response && !(error as AxiosError).isAxiosError) {
        throw error
      }

      const axiosError = error as AxiosError

      if (attempt < config.maxRetries && shouldRetry(axiosError, config)) {
        const delayMs = config.retryDelay * Math.pow(2, attempt)
        console.warn(`Retry attempt ${attempt + 1}/${config.maxRetries} after ${delayMs}ms`)
        await delay(delayMs)
        continue
      }

      // Більше спроб немає
      throw error
    }
  }

  throw lastError
}

export default {
  addRetryInterceptor,
  retryRequest
}
