# Приклади використання покращеної обробки помилок

## 1. Базове використання з useErrorHandler

```vue
<template>
  <div>
    <ErrorDisplay v-if="error" :error="error" @dismiss="clearError" />

    <button @click="fetchPatients" :disabled="isLoading">
      {{ isLoading ? 'Завантаження...' : 'Завантажити пацієнтів' }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useErrorHandler } from '@/composables/useErrorHandler'
import ErrorDisplay from '@/components/ErrorDisplay.vue'
import apiClient from '@/services/apiClient'

const patients = ref([])
const { error, isLoading, executeWithErrorHandling } = useErrorHandler()

async function fetchPatients() {
  const result = await executeWithErrorHandling(() => apiClient.get('/patients'))

  if (result) {
    patients.value = result.data
  }
}
</script>
```

## 2. Форма з валідацією

```vue
<template>
  <form @submit.prevent="submitForm">
    <ErrorDisplay
      v-if="error"
      :error="error"
      title="Помилка валідації"
      errorType="warning"
      @dismiss="clearError"
    />

    <div>
      <label>Ім'я пацієнта</label>
      <input
        v-model="form.full_name"
        type="text"
        :class="{ 'border-red-500': getFieldError('full_name') }"
      />
      <p v-if="getFieldError('full_name')" class="text-red-500 text-sm">
        {{ getFieldError('full_name') }}
      </p>
    </div>

    <div>
      <label>Email</label>
      <input
        v-model="form.email"
        type="email"
        :class="{ 'border-red-500': getFieldError('email') }"
      />
      <p v-if="getFieldError('email')" class="text-red-500 text-sm">
        {{ getFieldError('email') }}
      </p>
    </div>

    <button type="submit" :disabled="isLoading">
      {{ isLoading ? 'Збереження...' : 'Зберегти' }}
    </button>
  </form>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useErrorHandler } from '@/composables/useErrorHandler'
import ErrorDisplay from '@/components/ErrorDisplay.vue'
import apiClient from '@/services/apiClient'

const form = ref({
  full_name: '',
  email: ''
})

const { error, isLoading, clearError, getFieldError, executeWithErrorHandling } = useErrorHandler()

async function submitForm() {
  const result = await executeWithErrorHandling(() => apiClient.post('/patients', form.value))

  if (result) {
    // Успішно створено
    console.log('Пацієнт створено:', result.data)
    // Очистити форму або перенаправити
  }
}
</script>
```

## 3. Ручна обробка помилок

```vue
<script setup lang="ts">
import { useErrorHandler } from '@/composables/useErrorHandler'
import apiClient from '@/services/apiClient'

const { handleError } = useErrorHandler()

async function deletePatient(id: number) {
  try {
    await apiClient.delete(`/patients/${id}`)
    console.log('Пацієнт видалено')
  } catch (err) {
    // Обробити помилку з відображенням нотифікації
    handleError(err as AxiosError, true)
  }
}

async function getPatientSilently(id: number) {
  try {
    const response = await apiClient.get(`/patients/${id}`)
    return response.data
  } catch (err) {
    // Обробити помилку БЕЗ відображення нотифікації
    handleError(err as AxiosError, false)
    return null
  }
}
</script>
```

## 4. Використання retry для критичних запитів

```vue
<script setup lang="ts">
import { retryRequest } from '@/utils/retryHelper'
import apiClient from '@/services/apiClient'

async function fetchCriticalData() {
  try {
    const response = await retryRequest(() => apiClient.get('/critical-endpoint'), {
      maxRetries: 5,
      retryDelay: 2000,
      retryableStatuses: [408, 500, 502, 503, 504]
    })
    return response.data
  } catch (err) {
    console.error('Failed after 5 retries:', err)
  }
}
</script>
```

## 5. Глобальні нотифікації (налаштування в App.vue)

```vue
<template>
  <div id="app">
    <!-- Ваш основний контент -->
    <router-view />

    <!-- Глобальні нотифікації -->
    <div class="fixed top-4 right-4 z-50 space-y-2">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        class="notification"
        :class="notification.type"
      >
        <strong>{{ notification.title }}</strong>
        <p>{{ notification.message }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ErrorHandler } from '@/utils/errorHandler'

interface Notification {
  id: number
  title: string
  message: string
  type: 'error' | 'warning' | 'info'
}

const notifications = ref<Notification[]>([])
let notificationId = 0

function showNotification(notification: {
  title: string
  message: string
  type: 'error' | 'warning' | 'info'
  duration?: number
}) {
  const id = notificationId++
  notifications.value.push({ ...notification, id })

  setTimeout(() => {
    notifications.value = notifications.value.filter((n) => n.id !== id)
  }, notification.duration || 4000)
}

onMounted(() => {
  // Встановити callback для ErrorHandler
  ErrorHandler.setNotificationCallback(showNotification)
})
</script>

<style scoped>
.notification {
  padding: 1rem;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  min-width: 300px;
}

.notification.error {
  background-color: #fee;
  border: 1px solid #fcc;
  color: #c00;
}

.notification.warning {
  background-color: #ffc;
  border: 1px solid #fc0;
  color: #860;
}

.notification.info {
  background-color: #eff;
  border: 1px solid #0cc;
  color: #088;
}
</style>
```

## 6. Кастомні типи для TypeScript

```typescript
// types/api.ts
import type { ApiError } from '@/utils/errorHandler'

export interface Patient {
  id: number
  full_name: string
  email: string
  phone: string
}

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  per_page: number
  total: number
}

// Використання
async function fetchPatient(id: number): Promise<Patient | null> {
  const result = await executeWithErrorHandling<ApiResponse<Patient>>(() =>
    apiClient.get(`/patients/${id}`)
  )

  return result ? result.data.data : null
}
```

## Переваги нової системи обробки помилок

1. **Централізована обробка** - всі помилки обробляються однаково
2. **Автоматичні retry** - мережеві помилки автоматично повторюються
3. **Типізація** - повна підтримка TypeScript
4. **Гнучкість** - можна показувати або приховувати нотифікації
5. **Валідація** - зручна робота з помилками валідації форм
6. **User-friendly** - зрозумілі повідомлення українською мовою
7. **Статус-коди** - обробка всіх HTTP статус-кодів
8. **Loading states** - вбудована підтримка індикаторів завантаження
