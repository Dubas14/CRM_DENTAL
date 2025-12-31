import { ref } from 'vue'
import type { AxiosError } from 'axios'
import { ErrorHandler, type ApiError } from '@/utils/errorHandler'

/**
 * Composable для обробки помилок у компонентах
 */
export function useErrorHandler() {
  const error = ref<ApiError | null>(null)
  const isLoading = ref(false)

  /**
   * Очистити помилку
   */
  function clearError() {
    error.value = null
  }

  /**
   * Обробити помилку
   */
  function handleError(err: AxiosError<ApiError>, showNotification: boolean = true): ApiError {
    const apiError = ErrorHandler.handle(err, showNotification)
    error.value = apiError
    return apiError
  }

  /**
   * Отримати повідомлення помилки для конкретного поля
   */
  function getFieldError(fieldName: string): string | null {
    return ErrorHandler.getFieldError(error.value, fieldName)
  }

  /**
   * Перевірити чи є помилки валідації
   */
  function hasValidationErrors(): boolean {
    return ErrorHandler.hasValidationErrors(error.value)
  }

  /**
   * Wrapper для виконання асинхронних операцій з обробкою помилок
   */
  async function executeWithErrorHandling<T>(
    operation: () => Promise<T>,
    options: {
      showNotification?: boolean
      loadingState?: boolean
    } = {}
  ): Promise<T | null> {
    const { showNotification = true, loadingState = true } = options

    try {
      if (loadingState) {
        isLoading.value = true
      }
      clearError()
      
      return await operation()
    } catch (err) {
      handleError(err as AxiosError<ApiError>, showNotification)
      return null
    } finally {
      if (loadingState) {
        isLoading.value = false
      }
    }
  }

  return {
    error,
    isLoading,
    clearError,
    handleError,
    getFieldError,
    hasValidationErrors,
    executeWithErrorHandling
  }
}

export default useErrorHandler
