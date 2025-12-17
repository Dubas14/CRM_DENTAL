<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import calendarApi from '../services/calendarApi';

const props = defineProps({
  doctorId: { type: [Number, String], required: true },
  procedureId: { type: [Number, String, null], default: null },
  equipmentId: { type: [Number, String, null], default: null },
  roomId: { type: [Number, String, null], default: null },
  assistantId: { type: [Number, String, null], default: null },
  date: { type: String, required: true },
  durationMinutes: { type: Number, default: null },
  autoLoad: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  refreshToken: { type: Number, default: 0 },
});

const emit = defineEmits(['select-slot']);

const slots = ref([]);
const recommended = ref([]);
const loading = ref(false);
const error = ref(null);
const reason = ref(null);
const slotRoom = ref(null);
const slotEquipment = ref(null);
const slotAssistantId = ref(null);

const hasNoSlots = computed(() => !loading.value && slots.value.length === 0);

const loadSlots = async () => {
  if (!props.date || !props.doctorId) return;
  loading.value = true;
  error.value = null;
  reason.value = null;
  try {
    const { data } = await calendarApi.getDoctorSlots(props.doctorId, {
      date: props.date,
      procedure_id: props.procedureId || undefined,
      equipment_id: props.equipmentId || undefined,
      room_id: props.roomId || undefined,
      assistant_id: props.assistantId || undefined,
    });
    slots.value = data.slots || [];
    reason.value = data.reason || null;
    slotRoom.value = data.room || null;
    slotEquipment.value = data.equipment || null;
    slotAssistantId.value = data.assistant_id || null;
    if (recommended.value.length === 0) {
      await loadRecommended();
    }
  } catch (e) {
    error.value = e.response?.data?.message || e.message;
  } finally {
    loading.value = false;
  }
};

const loadRecommended = async () => {
  if (!props.date || !props.doctorId) return;
  try {
    const { data } = await calendarApi.getRecommendedSlots(props.doctorId, {
      from_date: props.date,
      procedure_id: props.procedureId || undefined,
      equipment_id: props.equipmentId || undefined,
      room_id: props.roomId || undefined,
      assistant_id: props.assistantId || undefined,
    });
    recommended.value = data.slots || [];
  } catch (e) {
    // не блокуємо основний UI
    console.error('Не вдалося отримати рекомендації', e);
  }
};

const formatSlot = (slot) => `${slot.start} – ${slot.end}`;

watch(() => [props.doctorId, props.date, props.procedureId, props.equipmentId, props.roomId, props.assistantId], () => {
  if (props.autoLoad && !props.disabled) {
    loadSlots();
  }
});

watch(() => props.refreshToken, () => {
  if (props.autoLoad && !props.disabled) {
    loadSlots();
  }
});

onMounted(() => {
  if (props.autoLoad && !props.disabled) {
    loadSlots();
  }
});
</script>

<template>
  <div class="space-y-3 bg-slate-900/60 border border-slate-800 rounded-xl p-4">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs uppercase tracking-wide text-slate-400">Доступні слоти</p>
        <p class="text-lg font-semibold text-white">{{ date }}</p>
      </div>
      <button
        class="text-sm text-emerald-400 hover:text-emerald-300"
        :disabled="loading || disabled"
        @click="loadSlots"
      >
        Оновити
      </button>
    </div>

    <div v-if="slotRoom || slotEquipment || slotAssistantId" class="text-xs text-slate-400 bg-slate-800/60 border border-slate-700/60 rounded-lg p-3">
      <span v-if="slotRoom" class="mr-3">Кабінет: <strong class="text-sky-300">{{ slotRoom.name || `#${slotRoom.id}` }}</strong></span>
      <span v-if="slotEquipment" class="mr-3">Обладнання: <strong class="text-amber-300">{{ slotEquipment.name || `#${slotEquipment.id}` }}</strong></span>
      <span v-if="slotAssistantId">Асистент ID: <strong class="text-indigo-300">{{ slotAssistantId }}</strong></span>
    </div>

    <div v-if="error" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3">
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

      <div v-if="slots.length" class="grid sm:grid-cols-2 md:grid-cols-3 gap-2">
        <button
          v-for="slot in slots"
          :key="slot.start"
          :disabled="disabled"
          class="w-full px-3 py-2 rounded-lg border border-emerald-600/60 bg-emerald-900/30 text-white hover:bg-emerald-800/40 transition"
          @click="emit('select-slot', slot)"
        >
          {{ formatSlot(slot) }}
        </button>
      </div>

      <div v-else-if="loading" class="text-sm text-slate-400">Завантаження слотів...</div>

      <div v-else class="text-sm text-slate-400 bg-slate-800/60 border border-slate-700/60 rounded-lg p-3">
        <p class="font-semibold text-white mb-1">Вільних слотів немає</p>
        <p v-if="reason" class="text-xs uppercase tracking-wide text-amber-400">Причина: {{ reason }}</p>
        <p v-else class="text-xs text-slate-400">Обрати іншу дату або подивіться рекомендації нижче.</p>
      </div>

      <div class="border-t border-slate-800 pt-3 mt-3 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-sm font-semibold text-white">Рекомендовані найближчі</p>
          <button
            class="text-xs text-slate-400 hover:text-white"
            type="button"
            :disabled="disabled"
            @click="loadRecommended"
          >Оновити</button>
        </div>

        <div v-if="recommended.length" class="flex flex-wrap gap-2">
          <button
            v-for="slot in recommended"
            :key="`${slot.date}-${slot.start}`"
            :disabled="disabled"
            class="px-3 py-2 rounded-lg border border-slate-700 bg-slate-800/70 text-white hover:bg-slate-700/60 transition"
            @click="emit('select-slot', slot)"
          >
            <span class="text-xs text-slate-400 block">{{ slot.date }}</span>
            <span class="font-semibold">{{ formatSlot(slot) }}</span>
          </button>
        </div>
        <p v-else class="text-xs text-slate-500">Поки що немає рекомендацій</p>
      </div>
    </div>
  </div>
</template>
