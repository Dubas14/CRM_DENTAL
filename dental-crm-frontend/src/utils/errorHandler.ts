import type { AxiosError } from 'axios'

export interface ApiError {
  message: string
  error?: string
  errors?: Record<string, string[]>
  status?: number
}

export interface ErrorNotification {
  title: string
  message: string
  type: 'error' | 'warning' | 'info'
  duration?: number
}

/**
 * Централізований обробник помилок API
 */
export class ErrorHandler {
  private static notificationCallback?: (notification: ErrorNotification) => void

  /**
   * Встановити callback для відображення нотифікацій
   */
  static setNotificationCallback(callback: (notification: ErrorNotification) => void) {
    this.notificationCallback = callback
  }

  /**
   * Показати нотифікацію
   */
  private static notify(notification: ErrorNotification) {
    if (this.notificationCallback) {
      this.notificationCallback(notification)
    } else {
      // Fallback - показати alert
      console.error(notification.title, notification.message)
    }
  }

  /**
   * Обробити помилку мережі
   */
  private static handleNetworkError(): ErrorNotification {
    return {
      title: "Помилка з'єднання",
      message: "Не вдалося з'єднатися з сервером. Перевірте інтернет з'єднання.",
      type: 'error',
      duration: 5000
    }
  }

  /**
   * Обробити помилку валідації (422)
   */
  private static handleValidationError(data: ApiError): ErrorNotification {
    const errors = data.errors
    let message = data.message || 'Перевірте правильність введених даних'

    if (errors && Object.keys(errors).length > 0) {
      const firstError = Object.values(errors)[0]
      message = Array.isArray(firstError) ? firstError[0] : String(firstError)
    }

    return {
      title: 'Помилка валідації',
      message,
      type: 'warning',
      duration: 4000
    }
  }

  /**
   * Обробити помилку аутентифікації (401)
   */
  private static handleAuthError(): ErrorNotification {
    return {
      title: 'Необхідна аутентифікація',
      message: 'Ваша сесія закінчилася. Будь ласка, увійдіть знову.',
      type: 'warning',
      duration: 3000
    }
  }

  /**
   * Обробити помилку доступу (403)
   */
  private static handleForbiddenError(data: ApiError): ErrorNotification {
    return {
      title: 'Доступ заборонено',
      message: data.message || 'У вас немає прав для виконання цієї дії',
      type: 'error',
      duration: 4000
    }
  }

  /**
   * Обробити помилку не знайдено (404)
   */
  private static handleNotFoundError(data: ApiError): ErrorNotification {
    return {
      title: 'Не знайдено',
      message: data.message || 'Запитуваний ресурс не знайдено',
      type: 'warning',
      duration: 3000
    }
  }

  /**
   * Обробити помилку rate limiting (429)
   */
  private static handleRateLimitError(data: ApiError): ErrorNotification {
    return {
      title: 'Забагато запитів',
      message: data.message || 'Ви надіслали забагато запитів. Спробуйте пізніше.',
      type: 'warning',
      duration: 5000
    }
  }

  /**
   * Обробити серверну помилку (500)
   */
  private static handleServerError(data: ApiError): ErrorNotification {
    return {
      title: 'Помилка сервера',
      message: data.message || 'Сталася внутрішня помилка сервера. Спробуйте пізніше.',
      type: 'error',
      duration: 5000
    }
  }

  /**
   * Головний метод обробки помилок
   */
  static handle(error: AxiosError<ApiError>, showNotification: boolean = true): ApiError {
    let notification: ErrorNotification | null = null

    // Немає відповіді від сервера (мережева помилка)
    if (!error.response) {
      notification = this.handleNetworkError()
      if (showNotification) {
        this.notify(notification)
      }
      return {
        message: notification.message,
        error: 'network_error'
      }
    }

    const { status, data } = error.response

    // Обробка помилок за статус-кодом
    switch (status) {
      case 401:
        notification = this.handleAuthError()
        break
      case 403:
        notification = this.handleForbiddenError(data)
        break
      case 404:
        notification = this.handleNotFoundError(data)
        break
      case 422:
        notification = this.handleValidationError(data)
        break
      case 429:
        notification = this.handleRateLimitError(data)
        break
      case 500:
      case 502:
      case 503:
      case 504:
        notification = this.handleServerError(data)
        break
      default:
        notification = {
          title: 'Помилка',
          message: data.message || `Помилка ${status}`,
          type: 'error',
          duration: 4000
        }
    }

    if (showNotification && notification) {
      this.notify(notification)
    }

    return {
      message: data.message || notification.message,
      error: data.error,
      errors: data.errors,
      status
    }
  }

  /**
   * Витягнути повідомлення помилки валідації для конкретного поля
   */
  static getFieldError(apiError: ApiError | null, fieldName: string): string | null {
    if (!apiError?.errors || !apiError.errors[fieldName]) {
      return null
    }

    const fieldErrors = apiError.errors[fieldName]
    return Array.isArray(fieldErrors) ? fieldErrors[0] : String(fieldErrors)
  }

  /**
   * Перевірити чи є помилки валідації
   */
  static hasValidationErrors(apiError: ApiError | null): boolean {
    return !!(apiError?.errors && Object.keys(apiError.errors).length > 0)
  }
}

export default ErrorHandler
