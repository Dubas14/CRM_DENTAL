<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';
import calendarApi from '../services/calendarApi';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  booking: { type: Object, default: () => ({}) },
  bookingLoading: { type: Boolean, default: false },
  bookingError: { type: String, default: null },
  doctorId: { type: [String, Number], default: null },
  procedureId: { type: [String, Number], default: null },
  roomId: { type: [String, Number], default: null },
  equipmentId: { type: [String, Number], default: null },
  assistantId: { type: [String, Number], default: null },
  durationMinutes: { type: [String, Number], default: null },
  preferredTimeOfDay: { type: String, default: null },
});

const emit = defineEmits(['close', 'submit']);

const localBooking = reactive({ ...props.booking });
const suggestionSlots = ref([]);
const suggestionsLoading = ref(false);
const suggestionsError = ref(null);
const suggestionsFetched = ref(false);

watch(
    () => props.booking,
    (val) => {
      Object.assign(localBooking, val || {});
    },
    { deep: true }
);

const formattedStart = computed(() => localBooking.start ? new Date(localBooking.start).toLocaleString() : '');

const handleSubmit = () => {
  emit('submit', { ...localBooking });
};

const resolvedDoctorId = computed(() => localBooking.doctor_id ?? props.doctorId);
const resolvedProcedureId = computed(() => localBooking.procedure_id ?? props.procedureId);
const resolvedRoomId = computed(() => localBooking.room_id ?? props.roomId);
const resolvedEquipmentId = computed(() => localBooking.equipment_id ?? props.equipmentId);
const resolvedAssistantId = computed(() => localBooking.assistant_id ?? props.assistantId);
const resolvedDurationMinutes = computed(() => localBooking.duration_minutes ?? props.durationMinutes);
const resolvedPreferredTime = computed(() => localBooking.preferred_time_of_day ?? props.preferredTimeOfDay);

const buildSlotDate = (slot) => new Date(`${slot.date}T${slot.start}`);

const formatSlotLabel = (slot) => {
  const date = new Date(`${slot.date}T${slot.start}`);
  const dateLabel = date.toLocaleDateString('uk-UA', { day: '2-digit', month: 'short' });
  return `${dateLabel} • ${slot.start}–${slot.end}`;
};

const applySlot = (slot) => {
  localBooking.start = buildSlotDate(slot);
  localBooking.end = new Date(`${slot.date}T${slot.end}`);
};

const fetchSuggestions = async () => {
  suggestionsError.value = null;
  suggestionSlots.value = [];
  suggestionsFetched.value = false;

  if (!resolvedDoctorId.value) {
    suggestionsError.value = 'Оберіть лікаря, щоб підібрати час.';
    return;
  }

  suggestionsLoading.value = true;

  try {
    const fromDate = localBooking.start
      ? new Date(localBooking.start)
      : new Date();
    const payload = {
      doctor_id: resolvedDoctorId.value,
      from_date: fromDate.toISOString().slice(0, 10),
      procedure_id: resolvedProcedureId.value || undefined,
      room_id: resolvedRoomId.value || undefined,
      equipment_id: resolvedEquipmentId.value || undefined,
      assistant_id: resolvedAssistantId.value || undefined,
      duration_minutes: resolvedDurationMinutes.value || undefined,
      preferred_time_of_day: resolvedPreferredTime.value || undefined,
      limit: 6,
    };

    const { data } = await calendarApi.getBookingSuggestions(payload);
    suggestionSlots.value = data?.slots || [];
    suggestionsFetched.value = true;
  } catch (error) {
    suggestionsError.value = error.response?.data?.message || 'Не вдалося отримати слоти.';
  } finally {
    suggestionsLoading.value = false;
  }
};
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-text/20 dark:bg-bg/50 p-4">
    <div class="w-full max-w-lg bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 shadow-2xl overflow-hidden">
      <div class="p-4 bg-bg border-b border-border flex items-center justify-between">
        <div>
          <p class="text-text font-semibold">Створити запис</p>
          <p class="text-xs text-text/70">
            {{ formattedStart }}
          </p>
        </div>
        <button class="text-text/70 hover:text-text text-xl" @click="emit('close')">
          <X />
        </button>
      </div>

      <div class="p-4 space-y-3">
        <div v-if="bookingError" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3">
          {{ bookingError }}
        </div>

        <div class="rounded-lg border border-border/70 bg-bg/40 p-3 space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <p class="text-sm text-text font-medium">Швидкі слоти</p>
              <p class="text-xs text-text/60">Підібрані варіанти без ручного пошуку</p>
            </div>
            <button
                class="px-3 py-1.5 rounded bg-blue-600/90 hover:bg-blue-500 text-text text-xs"
                :disabled="suggestionsLoading"
                @click="fetchSuggestions"
            >
              {{ suggestionsLoading ? 'Підбираємо...' : '✨ Підібрати час' }}
            </button>
          </div>

          <div v-if="suggestionsError" class="text-xs text-red-400">
            {{ suggestionsError }}
          </div>

          <div v-if="suggestionSlots.length" class="grid gap-2 md:grid-cols-2">
            <button
                v-for="slot in suggestionSlots"
                :key="`${slot.date}-${slot.start}`"
                class="flex items-center justify-between rounded border border-border/70 bg-card/40 px-3 py-2 text-xs text-text hover:border-emerald-500 hover:text-emerald-200 transition-colors"
                @click="applySlot(slot)"
            >
              <span>{{ formatSlotLabel(slot) }}</span>
              <span class="text-emerald-300/80">Обрати</span>
            </button>
          </div>

          <p v-else class="text-xs text-text/60">
            {{ suggestionsFetched ? 'Немає доступних слотів на обраний період.' : 'Натисніть кнопку, щоб побачити рекомендації.' }}
          </p>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1 block">
            <span class="text-xs text-text/70">ID пацієнта</span>
            <input
                v-model="localBooking.patient_id"
                type="number"
                class="w-full bg-bg border border-border/80 rounded px-3 py-2 text-text"
                placeholder="Напр. 42"
            />
            <p class="text-xs text-text/60">Залиште порожнім для гостя</p>
          </label>

          <label class="space-y-1 block">
            <span class="text-xs text-text/70">Waitlist entry ID</span>
            <input
                v-model="localBooking.waitlist_entry_id"
                type="number"
                class="w-full bg-bg border border-border/80 rounded px-3 py-2 text-text"
                placeholder="Напр. 12"
            />
            <p class="text-xs text-text/60">Опційно</p>
          </label>
        </div>

        <label class="space-y-1 block">
          <span class="text-xs text-text/70">Коментар</span>
          <textarea
              v-model="localBooking.comment"
              rows="3"
              class="w-full bg-bg border border-border/80 rounded px-3 py-2 text-text"
              placeholder="Скарги, побажання, особливі вимоги..."
          ></textarea>
        </label>
      </div>

      <div class="p-4 border-t border-border flex justify-end gap-2">
        <button
            class="px-4 py-2 rounded border border-border/80 text-text/90 hover:text-text"
            @click="emit('close')"
        >
          Скасувати
        </button>
        <button
            class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-text disabled:opacity-60"
            :disabled="bookingLoading"
            @click="handleSubmit"
        >
          {{ bookingLoading ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </div>
  </div>
</template>
