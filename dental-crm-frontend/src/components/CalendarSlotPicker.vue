<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { debounce } from 'lodash-es'
import calendarApi from '../services/calendarApi'

const props = defineProps({
  doctorId: { type: [Number, String], required: true },
  procedureId: { type: [Number, String, null], default: null },
  equipmentId: { type: [Number, String, null], default: null },
  roomId: { type: [Number, String, null], default: null },
  assistantId: { type: [Number, String, null], default: null },
  assistants: { type: Array, default: () => [] },
  date: { type: String, required: true },
  durationMinutes: { type: Number, default: null },
  autoLoad: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  refreshToken: { type: Number, default: 0 }
})

const emit = defineEmits(['select-slot'])

const slots = ref([])
const recommended = ref([])
const loading = ref(false)
const error = ref(null)
const reason = ref(null)
const slotRoom = ref(null)
const slotEquipment = ref(null)
const slotAssistantId = ref(null)

// Guard to prevent concurrent requests
const isFetchingSlots = ref(false)

// hasNoSlots removed
const normalizedReason = computed(() => (reason.value ? String(reason.value).toLowerCase() : null))
const isNoSchedule = computed(() => normalizedReason.value === 'no_schedule')
const isNoRoomCompatibility = computed(() => normalizedReason.value === 'no_room_compatibility')

// Tick every minute so "today" slots update as time passes
const nowTick = ref(Date.now())
let nowInterval: any = null

const localDateString = () => {
  const d = new Date()
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

const timeToMinutes = (hhmm: string | null | undefined): number | null => {
  if (!hhmm || typeof hhmm !== 'string') return null
  const [hh, mm] = hhmm.split(':')
  const h = Number(hh)
  const m = Number(mm)
  if (Number.isNaN(h) || Number.isNaN(m)) return null
  return h * 60 + m
}

const isToday = computed(() => props.date === localDateString())
const nowMinutes = computed(() => {
  // depends on nowTick so it updates
  void nowTick.value
  const d = new Date()
  return d.getHours() * 60 + d.getMinutes()
})

// Hide past slots for "today"
const visibleSlots = computed(() => {
  if (!isToday.value) return slots.value
  return (slots.value || []).filter((s: any) => {
    const endMin = timeToMinutes(s?.end)
    if (endMin === null) return true
    return endMin > nowMinutes.value
  })
})

const visibleRecommended = computed(() => {
  const rec = recommended.value || []
  if (!isToday.value) return rec
  return rec.filter((s: any) => {
    const date = s?.date || props.date
    if (date !== props.date) return true
    const startMin = timeToMinutes(s?.start)
    if (startMin === null) return true
    return startMin > nowMinutes.value
  })
})

const reasonMessage = computed(() => {
  if (isNoSchedule.value) {
    return 'Лікар вихідний. Налаштуйте графік лікаря.'
  }
  if (isNoRoomCompatibility.value) {
    return 'Немає доступних сумісних кабінетів для вибраної процедури. Оберіть іншу процедуру або кабінет.'
  }
  if (normalizedReason.value === 'room_incompatible') {
    return 'Вибраний кабінет несумісний з процедурою. Оберіть інший кабінет.'
  }
  if (reason.value) {
    return `Причина: ${reason.value}`
  }
  return 'Обрати іншу дату або подивіться рекомендації нижче.'
})
const slotAssistantName = computed(() => {
  if (!slotAssistantId.value) return null
  const match = props.assistants.find(
    (assistant) => Number(assistant.id) === Number(slotAssistantId.value)
  )
  if (!match) return `#${slotAssistantId.value}`
  return (
    match.full_name ||
    match.name ||
    `${match.first_name || ''} ${match.last_name || ''}`.trim() ||
    `#${match.id}`
  )
})

const loadSlots = async () => {
  if (!props.date || !props.doctorId || isFetchingSlots.value) return
  isFetchingSlots.value = true
  loading.value = true
  error.value = null
  reason.value = null
  try {
    const { data } = await calendarApi.getDoctorSlots(props.doctorId, {
      date: props.date,
      procedure_id: props.procedureId || undefined,
      equipment_id: props.equipmentId || undefined,
      room_id: props.roomId || undefined,
      assistant_id: props.assistantId || undefined
    })
    slots.value = data.slots || []
    reason.value = data.reason || null
    slotRoom.value = data.room || null
    slotEquipment.value = data.equipment || null
    slotAssistantId.value = data.assistant_id || null
    if (recommended.value.length === 0) {
      await loadRecommended()
    }
  } catch (e) {
    error.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
    isFetchingSlots.value = false
  }
}

// Debounced version to prevent rate limiting
const debouncedLoadSlots = debounce(loadSlots, 500)

const loadRecommended = async () => {
  if (!props.date || !props.doctorId) return
  try {
    const { data } = await calendarApi.getRecommendedSlots(props.doctorId, {
      from_date: props.date,
      procedure_id: props.procedureId || undefined,
      equipment_id: props.equipmentId || undefined,
      room_id: props.roomId || undefined,
      assistant_id: props.assistantId || undefined
    })
    recommended.value = data.slots || []
  } catch (e) {
    // не блокуємо основний UI
    console.error('Не вдалося отримати рекомендації', e)
  }
}

const formatSlot = (slot) => `${slot.start} – ${slot.end}`

watch(
  () => [
    props.doctorId,
    props.date,
    props.procedureId,
    props.equipmentId,
    props.roomId,
    props.assistantId
  ],
  () => {
    if (props.autoLoad && !props.disabled) {
      debouncedLoadSlots()
    }
  }
)

watch(
  () => props.refreshToken,
  () => {
    if (props.autoLoad && !props.disabled) {
      // For refresh token, load immediately (not debounced) as it's intentional refresh
      loadSlots()
    }
  }
)

onMounted(() => {
  if (props.autoLoad && !props.disabled) {
    // Initial load - use debounced version to avoid immediate rate limit
    debouncedLoadSlots()
  }
  nowInterval = setInterval(() => {
    nowTick.value = Date.now()
  }, 60_000)
})

onUnmounted(() => {
  if (nowInterval) {
    clearInterval(nowInterval)
    nowInterval = null
  }
})
</script>

<template>
  <div class="space-y-3 bg-card/60 rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs uppercase tracking-wide text-text/70">Доступні слоти</p>
        <p class="text-lg font-semibold text-text">{{ date }}</p>
      </div>
      <button
        class="text-sm text-emerald-400 hover:text-emerald-300"
        :disabled="loading || disabled"
        @click="loadSlots"
      >
        Оновити
      </button>
    </div>

    <div
      v-if="slotRoom || slotEquipment || slotAssistantId"
      class="text-xs text-text/70 bg-card/60 rounded-lg shadow-sm shadow-black/10 dark:shadow-black/40 p-3"
    >
      <span v-if="slotRoom" class="mr-3"
        >Кабінет:
        <strong class="text-sky-300">{{ slotRoom.name || `#${slotRoom.id}` }}</strong></span
      >
      <span v-if="slotEquipment" class="mr-3"
        >Обладнання:
        <strong class="text-amber-300">{{
          slotEquipment.name || `#${slotEquipment.id}`
        }}</strong></span
      >
      <span v-if="slotAssistantId"
        >Асистент: <strong class="text-indigo-300">{{ slotAssistantName }}</strong></span
      >
    </div>

    <div
      v-if="error"
      class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3"
    >
      {{ error }}
    </div>

    <div class="relative space-y-3">
      <div
        v-if="loading"
        class="absolute right-0 -top-1 text-xs text-emerald-300 flex items-center gap-2 animate-pulse"
      >
        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
        <span>Оновлення...</span>
      </div>

      <div v-if="visibleSlots.length" class="grid sm:grid-cols-2 md:grid-cols-3 gap-2">
        <button
          v-for="slot in visibleSlots"
          :key="slot.start"
          :disabled="disabled"
          class="w-full px-3 py-2 rounded-lg border border-emerald-600/60 bg-emerald-900/30 text-text hover:bg-emerald-800/40 transition"
          @click="emit('select-slot', slot)"
        >
          {{ formatSlot(slot) }}
        </button>
      </div>

      <div v-else-if="loading" class="text-sm text-text/70">Завантаження слотів...</div>

      <div v-else class="text-sm text-text/70 bg-card/60 rounded-lg shadow-sm shadow-black/10 dark:shadow-black/40 p-3">
        <p class="font-semibold text-text mb-1">Вільних слотів немає</p>
        <p class="text-xs text-amber-400">
          {{ isToday && slots.length ? 'Слоти на сьогодні вже минули. Оберіть іншу дату.' : reasonMessage }}
        </p>
      </div>

      <div class="border-t border-border pt-3 mt-3 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-text">Рекомендовані найближчі</p>
          <button
            class="text-xs text-text/70 hover:text-text"
            type="button"
            :disabled="disabled"
            @click="loadRecommended"
          >
            Оновити
          </button>
        </div>

        <div v-if="visibleRecommended.length" class="flex flex-wrap gap-2">
          <button
            v-for="slot in visibleRecommended"
            :key="`${slot.date}-${slot.start}`"
            :disabled="disabled"
            class="px-3 py-2 rounded-lg border border-border/80 bg-card/70 text-text hover:bg-card/60 transition"
            @click="emit('select-slot', slot)"
          >
            <span class="text-xs text-text/70 block">{{ slot.date }}</span>
            <span class="font-semibold">{{ formatSlot(slot) }}</span>
          </button>
        </div>
        <p v-else class="text-xs text-text/60">Поки що немає рекомендацій</p>
      </div>
    </div>
  </div>
</template>
