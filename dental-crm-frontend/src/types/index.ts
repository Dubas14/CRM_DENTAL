export * from './api'

// Common utility types
export type Nullable<T> = T | null
export type Optional<T> = T | undefined
export type Maybe<T> = T | null | undefined

// Form types
export interface FormField<T = any> {
  value: T
  error: string | null
  touched: boolean
  dirty: boolean
}

export interface FormState<T extends Record<string, any>> {
  fields: {
    [K in keyof T]: FormField<T[K]>
  }
  isValid: boolean
  isSubmitting: boolean
  errors: Record<string, string>
}

// Loading states
export interface LoadingState {
  isLoading: boolean
  error: string | null
}

export interface DataState<T> extends LoadingState {
  data: T | null
}

// Toast/Notification types
export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: string
  type: ToastType
  message: string
  duration?: number
}

// Calendar types
export interface CalendarEvent {
  id: number | string
  title: string
  start: Date | string
  end: Date | string
  color?: string
  data?: any
}

// Filter types
export interface FilterOption<T = any> {
  label: string
  value: T
}

export interface DateRange {
  from: string | Date
  to: string | Date
}

// Pagination types
export interface PaginationParams {
  page?: number
  per_page?: number
}

export interface SortParams {
  sort_by?: string
  sort_order?: 'asc' | 'desc'
}

export interface SearchParams {
  search?: string
}

export type QueryParams = PaginationParams & SortParams & SearchParams & Record<string, any>

