<script setup>
import { onMounted, ref, watch } from 'vue';
import calendarApi from '../services/calendarApi';

const props = defineProps({
  clinicId: { type: [Number, String], required: true },
  doctorId: { type: [Number, String, null], default: null },
  procedureId: { type: [Number, String, null], default: null },
  preferredDate: { type: String, default: null },
  limit: { type: Number, default: 5 },
  autoLoad: { type: Boolean, default: true },
});

const emit = defineEmits(['booked', 'refresh']);

const loading = ref(false);
const error = ref(null);
const candidates = ref([]);

const loadCandidates = async () => {
  if (!props.clinicId) return;
  loading.value = true;
  error.value = null;
  try {
    const { data } = await calendarApi.getWaitlistCandidates({
      clinic_id: props.clinicId,
      doctor_id: props.doctorId || undefined,
      procedure_id: props.procedureId || undefined,
      preferred_date: props.preferredDate || undefined,
      limit: props.limit,
    });
    candidates.value = data || [];
  } catch (e) {
    error.value = e.response?.data?.message || e.message;
  } finally {
    loading.value = false;
  }
};

const markBooked = async (entry) => {
  try {
    await calendarApi.markWaitlistBooked(entry.id);
    emit('booked', entry);
    loadCandidates();
  } catch (e) {
    error.value = e.response?.data?.message || e.message;
  }
};

watch(
  () => [props.clinicId, props.doctorId, props.procedureId, props.preferredDate, props.limit],
  () => {
    if (props.autoLoad) loadCandidates();
  },
);

onMounted(() => {
  if (props.autoLoad) loadCandidates();
});
</script>

<template>
  <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-xs uppercase tracking-wide text-slate-400">Список очікування</p>
        <p class="text-lg font-semibold text-white">Кандидати на слот</p>
      </div>
      <button class="text-sm text-emerald-400 hover:text-emerald-300" @click="loadCandidates">Оновити</button>
    </div>

    <div v-if="error" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3">{{ error }}</div>
    <div v-if="loading" class="text-sm text-slate-300">Завантаження кандидатів...</div>

    <div v-else>
      <div v-if="candidates.length" class="divide-y divide-slate-800">
        <div v-for="candidate in candidates" :key="candidate.id" class="py-3 flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-white font-semibold">{{ candidate.patient?.full_name || 'Пацієнт' }}</p>
              <p class="text-xs text-slate-400">
                {{ candidate.procedure?.name || 'Процедура не вказана' }}
                <span v-if="candidate.doctor" class="text-slate-500">• {{ candidate.doctor.full_name }}</span>
              </p>
            </div>
            <button
              class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-sm rounded-lg"
              @click="markBooked(candidate)"
            >
              Забронювати
            </button>
          </div>
          <div class="flex items-center gap-3 text-xs text-slate-400">
            <span v-if="candidate.preferred_date" class="bg-slate-800/60 px-2 py-1 rounded">{{ candidate.preferred_date }}</span>
            <span class="bg-slate-800/60 px-2 py-1 rounded">Статус: {{ candidate.status }}</span>
          </div>
        </div>
      </div>
      <p v-else class="text-sm text-slate-500">Кандидатів поки що немає</p>
    </div>
  </div>
</template>
