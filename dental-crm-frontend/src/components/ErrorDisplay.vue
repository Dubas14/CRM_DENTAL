<template>
  <div v-if="error" class="error-display" :class="errorTypeClass">
    <div class="flex items-start">
      <div class="flex-shrink-0">
        <svg
          v-if="errorType === 'error'"
          class="h-5 w-5 text-red-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"
          />
        </svg>
        <svg
          v-else
          class="h-5 w-5 text-yellow-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
      <div class="ml-3 flex-1">
        <h3 class="text-sm font-medium" :class="textClass">
          {{ title }}
        </h3>
        <div class="mt-2 text-sm" :class="textClass">
          <p>{{ error.message }}</p>

          <!-- Validation errors -->
          <ul v-if="hasValidationErrors" class="list-disc list-inside mt-2 space-y-1">
            <li v-for="(messages, field) in error.errors" :key="field">
              <strong>{{ field }}:</strong>
              {{ Array.isArray(messages) ? messages[0] : messages }}
            </li>
          </ul>
        </div>
      </div>
      <div v-if="dismissible" class="ml-auto pl-3">
        <button
          type="button"
          @click="$emit('dismiss')"
          class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2"
          :class="dismissButtonClass"
        >
          <span class="sr-only">Закрити</span>
          <svg
            class="h-5 w-5"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
          >
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ApiError } from '@/utils/errorHandler'

interface Props {
  error: ApiError | null
  title?: string
  errorType?: 'error' | 'warning'
  dismissible?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Помилка',
  errorType: 'error',
  dismissible: true
})

defineEmits<{
  dismiss: []
}>()

const hasValidationErrors = computed(() => {
  return !!(props.error?.errors && Object.keys(props.error.errors).length > 0)
})

const errorTypeClass = computed(() => {
  return props.errorType === 'error'
    ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'
    : 'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800'
})

const textClass = computed(() => {
  return props.errorType === 'error'
    ? 'text-red-800 dark:text-red-200'
    : 'text-yellow-800 dark:text-yellow-200'
})

const dismissButtonClass = computed(() => {
  return props.errorType === 'error'
    ? 'text-red-500 hover:bg-red-100 dark:hover:bg-red-800 focus:ring-red-600'
    : 'text-yellow-500 hover:bg-yellow-100 dark:hover:bg-yellow-800 focus:ring-yellow-600'
})
</script>

<style scoped>
.error-display {
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1rem;
}
</style>

