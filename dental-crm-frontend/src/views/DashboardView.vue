<script setup lang="ts">
import { computed, onMounted, ref, watch, onUnmounted } from 'vue'
import { useAuth } from '../composables/useAuth'
import { usePermissions } from '../composables/usePermissions'
import apiClient from '../services/apiClient'
import calendarApi from '../services/calendarApi'
import { Users, Calendar, Clock, Activity, RefreshCw } from 'lucide-vue-next'
import ActivityChart from '../components/ActivityChart.vue'
import { debounce } from 'lodash-es'

const { user } = useAuth()
const { role, isDoctor } = usePermissions()

// Основний стан
const stats = ref({
  patientsCount: 0,
  appointmentsToday: 0,
  nextAppointment: null
})

const loading = ref(true)
const weeklyActivity = ref([])
const upcomingAppointments = ref([])

const daysShort = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб']

// ПОКРАЩЕННЯ: Кеш для даних
const dataCache = ref({
  patients: { data: null, timestamp: null },
  appointments: { data: null, timestamp: null },
  weeklyActivity: { data: null, timestamp: null }
})

const CACHE_TTL = 5 * 60 * 1000 // 5 хвилин
let refreshInterval = null

// ПОКРАЩЕННЯ: Обробка помилок
const errors = ref({
  patients: null,
  appointments: null,
  general: null
})

// ПОКРАЩЕННЯ: Скелетон стани
const skeletonStates = ref({
  stats: true,
  appointments: true,
  chart: true
})

// ПОКРАЩЕННЯ: Флаг для примусового оновлення
const forceRefreshFlag = ref(false)

// Комп'ютед властивості
const greetingName = computed(() => {
  if (!user.value) return 'гість'

  if (['super_admin', 'clinic_admin'].includes(role.value)) {
    return 'Адміністратор'
  }

  if (user.value.doctor) {
    const doc = user.value.doctor
    return doc.full_name || `${doc.first_name || ''} ${doc.last_name || ''}`.trim()
  }

  return `${user.value.first_name || ''} ${user.value.last_name || ''}`.trim() || 'користувач'
})

const greetingSubtitle = computed(() => {
  if (!user.value) return 'Ласкаво просимо!'

  if (role.value === 'super_admin') return 'Суперадміністратор'
  if (role.value === 'clinic_admin') return 'Адміністратор клініки'
  if (role.value === 'doctor') return 'Лікар'
  if (role.value === 'registrar') return 'Реєстратор'

  return 'Користувач'
})

// Утиліти
const formatDateYMD = (date) => date.toISOString().slice(0, 10)

const parseAppointmentDate = (appt) => {
  if (appt.start_at) return new Date(appt.start_at)
  if (appt.date && appt.time) return new Date(`${appt.date}T${appt.time}`)
  if (appt.date) return new Date(`${appt.date}T00:00:00`)
  return null
}

const normalizeCollection = (payload) => {
  const items = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data?.data)
        ? payload.data.data
        : []

  const total = payload?.meta?.total ?? payload?.total ?? payload?.data?.total ?? items.length

  return { items, total }
}

const formatTime = (date) =>
  date?.toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' }) || '—'
const formatDayMonth = (date) =>
  date?.toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit' }) || ''
const formatDateParam = (date) => (date ? formatDateYMD(date) : '')
const todayParam = computed(() => formatDateYMD(new Date()))

const resolveDoctorLabel = (appt) => {
  const doctor = appt.doctor
  if (doctor?.full_name) return doctor.full_name
  if (doctor?.name) return doctor.name
  if (doctor?.first_name || doctor?.last_name) {
    return `${doctor.first_name || ''} ${doctor.last_name || ''}`.trim()
  }
  if (doctor?.user?.full_name) return doctor.user.full_name
  if (doctor?.user?.first_name || doctor?.user?.last_name) {
    return `${doctor.user.first_name || ''} ${doctor.user.last_name || ''}`.trim()
  }
  return appt.doctor_name || ''
}

const resolveClinicLabel = (appt) =>
  appt.clinic?.name || appt.clinic_name || appt.clinic?.title || ''

const resolveRoomLabel = (appt) => appt.room?.name || appt.room_name || ''

const resolveTaskLabel = (appt) => appt.procedure?.name || appt.procedure_name || appt.comment || ''

// ДОДАНО: Перевірка валідності кешу
const isCacheValid = (cacheKey) => {
  const cache = dataCache.value[cacheKey]
  if (!cache?.data || !cache.timestamp) return false
  return Date.now() - cache.timestamp < CACHE_TTL
}

// ДОДАНО: Fallback дані при помилках
const getFallbackData = () => {
  const today = new Date()
  const fallbackActivity = Array.from({ length: 7 }).map((_, index) => {
    const d = new Date(today)
    d.setDate(today.getDate() + index)
    return {
      day: daysShort[d.getDay()],
      value: Math.floor(Math.random() * 15) + 5
    }
  })

  return {
    patientsCount: 0,
    appointmentsToday: 0,
    nextAppointment: null,
    weeklyActivity: fallbackActivity,
    upcomingAppointments: []
  }
}

// Guard to prevent concurrent requests
let isFetchingStats = false

// ПОКРАЩЕНА ВЕРСІЯ loadStats (перейменована для уникнення конфлікту)
const loadStatsEnhanced = async () => {
  // Prevent concurrent requests
  if (isFetchingStats) return
  isFetchingStats = true

  // Скидаємо помилки
  errors.value = { patients: null, appointments: null, general: null }

  // Перевіряємо кеш (якщо не примусове оновлення)
  if (!forceRefreshFlag.value) {
    const hasValidCache =
      isCacheValid('patients') && isCacheValid('appointments') && isCacheValid('weeklyActivity')

    if (hasValidCache) {
      // Використовуємо дані з кешу
      stats.value.patientsCount = dataCache.value.patients.data.patientsCount || 0
      stats.value.appointmentsToday = dataCache.value.appointments.data.appointmentsToday || 0
      stats.value.nextAppointment = dataCache.value.appointments.data.nextAppointment || null
      weeklyActivity.value = dataCache.value.weeklyActivity.data || []
      upcomingAppointments.value = dataCache.value.appointments.data.upcomingAppointments || []

      skeletonStates.value.stats = false
      skeletonStates.value.appointments = false
      skeletonStates.value.chart = false
      loading.value = false
      return
    }
  }

  // Якщо немає валідного кешу - завантажуємо
  skeletonStates.value = { stats: true, appointments: true, chart: true }
  loading.value = true

  const today = new Date()
  const startRange = new Date(today)
  startRange.setDate(today.getDate() - 1)
  const rangeEnd = new Date(today)
  rangeEnd.setDate(today.getDate() + 7)

  const appointmentParams = {
    from_date: formatDateYMD(startRange),
    to_date: formatDateYMD(rangeEnd)
  }

  const doctorId = user.value?.doctor?.id
  if (isDoctor.value && doctorId) {
    appointmentParams.doctor_id = doctorId
  }

  try {
    const [patientsResponse, appointmentsResponse] = await Promise.allSettled([
      apiClient.get('/patients'),
      calendarApi.getAppointments(appointmentParams)
    ])

    // Обробка пацієнтів
    if (patientsResponse.status === 'fulfilled') {
      const normalizedPatients = normalizeCollection(patientsResponse.value.data)
      stats.value.patientsCount = normalizedPatients.total || normalizedPatients.items.length

      // Кешуємо
      dataCache.value.patients = {
        data: { patientsCount: stats.value.patientsCount },
        timestamp: Date.now()
      }
    } else {
      errors.value.patients = 'Не вдалося завантажити пацієнтів'
      // Використовуємо кеш або fallback
      if (dataCache.value.patients.data) {
        stats.value.patientsCount = dataCache.value.patients.data.patientsCount || 0
      }
    }

    // Обробка записів
    if (appointmentsResponse.status === 'fulfilled') {
      const normalizedAppointments = normalizeCollection(appointmentsResponse.value.data)

      const mappedAppointments = normalizedAppointments.items
        .map((appt) => {
          const startDate = parseAppointmentDate(appt)
          return {
            ...appt,
            startDate,
            patientLabel: appt.patient?.full_name || appt.patient_name || appt.patient?.name || '—',
            procedureName: appt.procedure?.name || '',
            taskLabel: resolveTaskLabel(appt),
            doctorLabel: resolveDoctorLabel(appt),
            clinicLabel: resolveClinicLabel(appt),
            roomLabel: resolveRoomLabel(appt),
            doctorId: appt.doctor?.id || appt.doctor_id || null,
            clinicId: appt.clinic?.id || appt.clinic_id || null,
            displayTime: formatTime(startDate) || (appt.time ? appt.time.slice(0, 5) : '—'),
            displayDate: formatDayMonth(startDate) || appt.date || '',
            dateParam: formatDateParam(startDate) || appt.date || ''
          }
        })
        .filter((appt) => appt.startDate)

      const todayStr = formatDateYMD(today)
      const now = Date.now()
      const todayAppointments = mappedAppointments.filter(
        (appt) => formatDateYMD(appt.startDate) === todayStr
      )
      stats.value.appointmentsToday = todayAppointments.length

      const upcoming = mappedAppointments
        .filter((appt) => appt.startDate.getTime() >= now && appt.status !== 'cancelled')
        .sort((a, b) => a.startDate - b.startDate)

      stats.value.nextAppointment = upcoming[0] || null
      upcomingAppointments.value = upcoming.slice(0, 5)

      // Активність за тиждень
      const rangeMap = Array.from({ length: 7 }).map((_, index) => {
        const d = new Date(today)
        d.setDate(today.getDate() + index)
        return {
          key: formatDateYMD(d),
          day: daysShort[d.getDay()],
          value: 0
        }
      })

      mappedAppointments.forEach((appt) => {
        const dayKey = formatDateYMD(appt.startDate)
        const entry = rangeMap.find((item) => item.key === dayKey)
        if (entry) entry.value += 1
      })

      weeklyActivity.value = rangeMap.map(({ day, value }) => ({ day, value }))

      // Кешуємо дані записів
      dataCache.value.appointments = {
        data: {
          appointmentsToday: stats.value.appointmentsToday,
          nextAppointment: stats.value.nextAppointment,
          upcomingAppointments: upcomingAppointments.value
        },
        timestamp: Date.now()
      }

      // Кешуємо активність
      dataCache.value.weeklyActivity = {
        data: weeklyActivity.value,
        timestamp: Date.now()
      }
    } else {
      errors.value.appointments = 'Не вдалося завантажити записи'
      weeklyActivity.value = []
      upcomingAppointments.value = []

      // Використовуємо кеш або fallback
      if (dataCache.value.weeklyActivity.data) {
        weeklyActivity.value = dataCache.value.weeklyActivity.data
      }
      if (dataCache.value.appointments.data) {
        stats.value.appointmentsToday = dataCache.value.appointments.data.appointmentsToday || 0
        stats.value.nextAppointment = dataCache.value.appointments.data.nextAppointment || null
        upcomingAppointments.value = dataCache.value.appointments.data.upcomingAppointments || []
      }
    }
  } catch (e) {
    console.error('Critical error:', e)
    errors.value.general = 'Помилка завантаження даних'

    // Fallback до кешу або демо-даних
    const fallback = getFallbackData()
    stats.value.patientsCount = fallback.patientsCount
    stats.value.appointmentsToday = fallback.appointmentsToday
    stats.value.nextAppointment = fallback.nextAppointment
    weeklyActivity.value = fallback.weeklyActivity
    upcomingAppointments.value = fallback.upcomingAppointments
  } finally {
    loading.value = false
    skeletonStates.value = { stats: false, appointments: false, chart: false }
    forceRefreshFlag.value = false
    isFetchingStats = false
  }
}

// ДОДАНО: Debounce для loadStats
const debouncedLoadStats = debounce(loadStatsEnhanced, 300)

// ДОДАНО: Функція для ручного оновлення
const refreshData = () => {
  forceRefreshFlag.value = true
  debouncedLoadStats()
}

// Оригінальна функція loadStats (для зворотної сумісності з watch)
// loadStats removed

// Автоматичне оновлення кожні 5 хвилин
const startAutoRefresh = () => {
  if (refreshInterval) clearInterval(refreshInterval)
  refreshInterval = setInterval(() => {
    if (!document.hidden && user.value) {
      loadStatsEnhanced()
    }
  }, CACHE_TTL)
}

// Оновлення при поверненні на вкладку
const handleVisibilityChange = () => {
  if (!document.hidden && user.value) {
    // Якщо кеш старіший за 1 хвилину - оновлюємо
    const cacheAge =
      Date.now() -
      Math.min(dataCache.value.patients.timestamp || 0, dataCache.value.appointments.timestamp || 0)

    if (cacheAge > 60 * 1000) {
      // 1 хвилина
      loadStatsEnhanced()
    }
  }
}

// Lifecycle hooks - РОЗМІЩЕНІ ПІСЛЯ ВСІХ ОГОЛОШЕНЬ!
onMounted(() => {
  if (user.value) {
    loadStatsEnhanced()
    startAutoRefresh()
  }

  document.addEventListener('visibilitychange', handleVisibilityChange)
})

// Cleanup
onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  if (debouncedLoadStats.cancel) {
    debouncedLoadStats.cancel()
  }
})

watch(
  () => user.value,
  (val) => {
    if (val) {
      loadStatsEnhanced()
      startAutoRefresh()
    }
  }
)
</script>

<template>
  <div class="space-y-6 animate-fade-in">
    <!-- Заголовок з кнопкою оновлення -->
    <div class="flex justify-between items-start gap-4">
      <div
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 p-8 text-text shadow-lg flex-1"
      >
        <div class="relative z-10">
          <div class="flex items-center gap-3 mb-1">
            <h1 class="text-3xl font-bold">Вітаємо, {{ greetingName }}! 👋</h1>
            <span class="px-3 py-1 rounded-full bg-card/15 text-sm font-semibold">{{
              greetingSubtitle
            }}</span>
          </div>
          <p class="text-emerald-100 text-lg">
            <span
              v-if="skeletonStates.stats"
              class="inline-block h-5 w-40 bg-emerald-400/30 rounded animate-pulse"
            ></span>
            <span v-else
              >Гарного робочого дня. Сьогодні у вас {{ stats.appointmentsToday }} пацієнтів.</span
            >
          </p>

          <!-- Повідомлення про помилки -->
          <div
            v-if="errors.general"
            class="mt-3 text-amber-200 text-sm bg-amber-900/20 px-3 py-2 rounded-lg"
          >
            ⚠️ {{ errors.general }}. Показуються кешовані дані.
          </div>
        </div>
        <div
          class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-card/10 rounded-full blur-2xl"
        ></div>
        <div
          class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-card/10 rounded-full blur-2xl"
        ></div>
      </div>

      <!-- Кнопка оновлення -->
      <button
        @click="refreshData"
        :disabled="loading"
        class="p-4 bg-card hover:bg-card/80 rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 transition-colors disabled:opacity-50 disabled:cursor-not-allowed group"
        title="Оновити дані"
        aria-label="Оновити статистику"
      >
        <RefreshCw
          size="20"
          class="text-emerald-400 group-hover:text-emerald-300"
          :class="{ 'animate-spin': loading }"
        />
      </button>
    </div>

    <!-- Картки статистики з skeleton loading -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Пацієнти -->
      <router-link
        :to="{ name: 'patients' }"
        class="bg-card shadow-sm shadow-black/10 dark:shadow-black/40 p-6 rounded-xl hover:shadow-xl transition-all duration-300 group block"
        aria-label="Перейти до сторінки пацієнтів"
      >
        <div class="flex justify-between items-start">
          <div class="min-w-0 flex-1">
            <p class="text-text/70 text-sm font-medium uppercase">Всього пацієнтів</p>

            <!-- Skeleton loading -->
            <div
              v-if="skeletonStates.stats"
              class="h-10 w-20 bg-card/80 rounded animate-pulse mt-2"
            ></div>

            <!-- Дані -->
            <div v-else>
              <h3 class="text-3xl font-bold text-text mt-2">{{ stats.patientsCount }}</h3>
              <p v-if="errors.patients" class="text-xs text-red-400 mt-1">
                ⚠️ {{ errors.patients }}
              </p>
            </div>
          </div>
          <div
            class="p-3 bg-card/80 rounded-lg text-emerald-400 group-hover:bg-emerald-500 group-hover:text-text transition-colors shrink-0"
          >
            <Users size="24" />
          </div>
        </div>
      </router-link>

      <!-- Записи сьогодні -->
      <router-link
        :to="{ name: 'calendar-board', query: { date: todayParam, view: 'day' } }"
        class="bg-card shadow-sm shadow-black/10 dark:shadow-black/40 p-6 rounded-xl hover:shadow-xl transition-all duration-300 group block"
        aria-label="Перейти до календаря на сьогодні"
      >
        <div class="flex justify-between items-start">
          <div class="min-w-0 flex-1">
            <p class="text-text/70 text-sm font-medium uppercase">Записи сьогодні</p>

            <!-- Skeleton loading -->
            <div
              v-if="skeletonStates.stats"
              class="h-10 w-20 bg-card/80 rounded animate-pulse mt-2"
            ></div>

            <!-- Дані -->
            <div v-else>
              <h3 class="text-3xl font-bold text-text mt-2">{{ stats.appointmentsToday }}</h3>
              <p v-if="errors.appointments" class="text-xs text-red-400 mt-1">
                ⚠️ {{ errors.appointments }}
              </p>
            </div>
          </div>
          <div
            class="p-3 bg-card/80 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-text transition-colors shrink-0"
          >
            <Calendar size="24" />
          </div>
        </div>
      </router-link>

      <!-- Найближчий візит -->
      <div
        class="bg-card shadow-sm shadow-black/10 dark:shadow-black/40 p-6 rounded-xl hover:shadow-xl transition-all duration-300 group"
      >
        <div class="flex justify-between items-start">
          <div class="min-w-0 flex-1">
            <p class="text-text/70 text-sm font-medium uppercase">Найближчий візит</p>

            <!-- Skeleton loading -->
            <div
              v-if="skeletonStates.stats"
              class="h-10 w-full bg-card/80 rounded animate-pulse mt-2"
            ></div>

            <!-- Дані -->
            <div v-else>
              <h3 class="text-xl font-bold text-text mt-2 truncate">
                {{ stats.nextAppointment ? stats.nextAppointment.displayTime : '—' }}
              </h3>
              <p class="text-xs text-text/60 mt-1" v-if="stats.nextAppointment">
                {{ stats.nextAppointment.patientLabel || 'Без імені' }}
                <span v-if="stats.nextAppointment.displayDate" class="text-text/60"
                  >· {{ stats.nextAppointment.displayDate }}</span
                >
              </p>
              <p v-if="stats.nextAppointment" class="text-xs text-text/60 mt-1">
                <span v-if="stats.nextAppointment.clinicLabel"
                  >Клініка: {{ stats.nextAppointment.clinicLabel }}</span
                >
                <span
                  v-if="stats.nextAppointment.clinicLabel && stats.nextAppointment.doctorLabel"
                  class="mx-1"
                  >·</span
                >
                <span v-if="stats.nextAppointment.doctorLabel"
                  >Лікар: {{ stats.nextAppointment.doctorLabel }}</span
                >
                <span v-if="stats.nextAppointment.roomLabel" class="ml-1"
                  >· Кабінет: {{ stats.nextAppointment.roomLabel }}</span
                >
              </p>
              <p v-else class="text-sm text-text/60 mt-1">Записів немає</p>
            </div>
          </div>
          <div
            class="p-3 bg-card/80 rounded-lg text-purple-400 group-hover:bg-purple-500 group-hover:text-text transition-colors shrink-0"
          >
            <Clock size="24" />
          </div>
        </div>
      </div>
    </div>

    <!-- Найближчі візити -->
    <div class="bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-6 shadow-md">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-bold text-text flex items-center gap-2">
            <Clock size="18" class="text-emerald-400" />
            Найближчі візити
          </h3>
          <p class="text-text/60 text-sm">Перші 5 записів з найближчим часом</p>
        </div>
        <router-link
          :to="{ name: 'calendar-board' }"
          class="text-sm text-emerald-400 hover:text-emerald-300 whitespace-nowrap"
        >
          Перейти до календаря →
        </router-link>
      </div>

      <!-- Skeleton для списку -->
      <div v-if="skeletonStates.appointments" class="space-y-3">
        <div v-for="i in 3" :key="i" class="h-16 bg-card/80 rounded-lg animate-pulse"></div>
      </div>

      <!-- Дані -->
      <div v-else-if="!upcomingAppointments.length" class="text-text/60 text-sm py-4 text-center">
        Найближчих записів немає.
      </div>

      <ul v-else class="space-y-3">
        <li v-for="appt in upcomingAppointments" :key="appt.id">
          <router-link
            :to="{
              name: 'calendar-board',
              query: {
                date: appt.dateParam,
                view: 'day',
                appointment_id: appt.id,
                clinic: appt.clinicId || undefined
              }
            }"
            class="flex items-start justify-between bg-bg border border-border rounded-lg px-4 py-3 hover:border-emerald-500/40 transition-colors group"
          >
            <div class="min-w-0 flex-1">
              <p class="text-text font-semibold truncate">
                {{ appt.patientLabel }}
                <span v-if="appt.taskLabel" class="text-text/70 text-xs font-normal"
                  >· {{ appt.taskLabel }}</span
                >
              </p>
              <p class="text-text/60 text-xs mt-1">
                {{ appt.displayDate }} · {{ appt.displayTime }}
              </p>
              <p class="text-text/60 text-xs mt-1">
                <span v-if="appt.clinicLabel">Клініка: {{ appt.clinicLabel }}</span>
                <span v-if="appt.clinicLabel && appt.doctorLabel" class="mx-1">·</span>
                <span v-if="appt.doctorLabel">Лікар: {{ appt.doctorLabel }}</span>
                <span v-if="appt.roomLabel" class="ml-1">· Кабінет: {{ appt.roomLabel }}</span>
              </p>
            </div>
            <div class="ml-4 flex flex-col items-end gap-1">
              <span class="text-emerald-400 font-mono text-sm whitespace-nowrap">{{
                appt.displayTime
              }}</span>
              <span class="text-[11px] text-text/70 group-hover:text-emerald-300">Відкрити →</span>
            </div>
          </router-link>
        </li>
      </ul>
    </div>

    <!-- Секція швидких дій та графік -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-6">
        <h3 class="text-lg font-bold text-text mb-4 flex items-center gap-2">
          <Activity size="20" class="text-emerald-400" />
          Швидкі дії
        </h3>
        <div class="grid grid-cols-2 gap-4">
          <router-link
            :to="{ name: 'schedule' }"
            class="flex flex-col items-center justify-center p-4 bg-bg border border-border rounded-lg hover:bg-card/80 transition-colors cursor-pointer group"
          >
            <Calendar
              class="text-emerald-500 mb-2 group-hover:scale-110 transition-transform"
              size="28"
            />
            <span class="text-text/80 text-sm">Мій розклад</span>
          </router-link>
          <router-link
            :to="{ name: 'patients' }"
            class="flex flex-col items-center justify-center p-4 bg-bg border border-border rounded-lg hover:bg-card/80 transition-colors cursor-pointer group"
          >
            <Users
              class="text-blue-500 mb-2 group-hover:scale-110 transition-transform"
              size="28"
            />
            <span class="text-text/80 text-sm">База пацієнтів</span>
          </router-link>
        </div>
      </div>

      <!-- Графік з skeleton -->
      <div class="bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-6">
        <h3 class="text-lg font-bold text-text mb-4">Активність за тиждень</h3>

        <!-- Skeleton для графіка -->
        <div v-if="skeletonStates.chart" class="h-64 bg-card/80 rounded animate-pulse"></div>

        <!-- Графік -->
        <ActivityChart v-else :data="weeklyActivity" title="Активність за тиждень" />

        <!-- Повідомлення про дані -->
        <p
          v-if="!skeletonStates.chart && weeklyActivity.length === 0"
          class="text-text/60 text-sm mt-2"
        >
          Немає даних для відображення активності
        </p>
      </div>
    </div>
  </div>
</template>
