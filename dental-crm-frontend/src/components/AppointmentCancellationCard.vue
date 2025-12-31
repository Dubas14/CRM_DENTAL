<script setup lang="ts">
import { ref } from 'vue'
import calendarApi from '../services/calendarApi'

const props = defineProps({
  appointment: { type: Object, required: true }
})

const emit = defineEmits(['cancelled'])

const comment = ref('')
const loading = ref(false)
const error = ref(null)
const suggestions = ref([])

const cancelAppointment = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await calendarApi.cancelAppointment(props.appointment.id, {
      comment: comment.value || undefined
    })
    suggestions.value = data.waitlist_suggestions || []
    emit('cancelled', data.appointment)
  } catch (e) {
    error.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="bg-card/60 rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-3">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs uppercase tracking-wide text-text/70">Скасування запису</p>
        <p class="text-lg font-semibold text-text">
          {{ appointment?.patient?.full_name || 'Запис' }}
        </p>
      </div>
      <span class="text-xs bg-card/80 px-2 py-1 rounded text-text/80"
        >ID: {{ appointment.id }}</span
      >
    </div>

    <div
      v-if="error"
      class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3"
    >
      {{ error }}
    </div>

    <label class="space-y-1 block">
      <span class="text-sm text-text/80">Коментар до скасування</span>
      <textarea
        v-model="comment"
        name="comment"
        rows="2"
        class="w-full bg-bg border border-border/80 rounded-lg px-3 py-2 text-text"
        placeholder="Причина або інструкції для адміністратора"
      ></textarea>
    </label>

    <div class="flex justify-end gap-3">
      <button class="px-4 py-2 text-text/70 hover:text-text" type="button" @click="$emit('close')">
        Закрити
      </button>
      <button
        class="px-4 py-2 bg-red-600 hover:bg-red-500 text-text rounded-lg disabled:opacity-60"
        :disabled="loading"
        type="button"
        @click="cancelAppointment"
      >
        {{ loading ? 'Скасовую...' : 'Скасувати' }}
      </button>
    </div>

    <div v-if="suggestions.length" class="border-t border-border pt-3 mt-2 space-y-2">
      <p class="text-sm font-semibold text-text">Кандидати зі списку очікування</p>
      <div class="space-y-2">
        <div
          v-for="entry in suggestions"
          :key="entry.id"
          class="bg-card/60 rounded-lg shadow-sm shadow-black/10 dark:shadow-black/40 p-3"
        >
          <p class="text-text font-semibold">{{ entry.patient?.full_name || 'Пацієнт' }}</p>
          <p class="text-xs text-text/70">{{ entry.procedure?.name || 'Процедура не вказана' }}</p>
          <p class="text-xs text-text/60" v-if="entry.preferred_date">
            Бажана дата: {{ entry.preferred_date }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
