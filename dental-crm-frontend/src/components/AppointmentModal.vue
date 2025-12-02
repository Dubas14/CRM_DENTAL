<script setup>
import { ref, computed } from 'vue';
import apiClient from '../services/apiClient';

const props = defineProps({
  appointment: Object, // Об'єкт запису
  isOpen: Boolean
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
  diagnosis: '',
  treatment: '',
  complaints: '',
  tooth_number: '',
  update_tooth_status: ''
});

const loading = ref(false);

const statuses = [
  { id: 'healthy', label: 'Здоровий' },
  { id: 'caries', label: 'Карієс' },
  { id: 'filled', label: 'Пломба' },
  { id: 'pulpitis', label: 'Пульпіт' },
  { id: 'missing', label: 'Відсутній' },
];

const getProp = (key) => {
  if (!props.appointment) return null;
  if (props.appointment[key] !== undefined) return props.appointment[key];
  if (props.appointment.extendedProps && props.appointment.extendedProps[key] !== undefined) {
    return props.appointment.extendedProps[key];
  }
  return null;
};

const patientName = computed(() => getProp('patient_name') || getProp('comment') || 'Пацієнт');
const patientId = computed(() => getProp('patient_id'));
const appointmentId = computed(() => props.appointment?.id);
const status = computed(() => getProp('status'));

const saveRecord = async () => {
  if (!form.value.diagnosis || !form.value.treatment) {
    alert('Заповніть діагноз та лікування');
    return;
  }

  if (!patientId.value) {
    alert('Помилка: Цей запис не привʼязаний до пацієнта в базі (немає ID). Створіть пацієнта спочатку.');
    return;
  }

  loading.value = true;
  try {
    await apiClient.post(`/patients/${patientId.value}/records`, {
      ...form.value,
      appointment_id: appointmentId.value
    });

    alert('Прийом завершено успішно!');
    emit('saved');
    emit('close');

    // Очистка форми
    form.value = { diagnosis: '', treatment: '', complaints: '', tooth_number: '', update_tooth_status: '' };
  } catch (e) {
    alert('Помилка: ' + (e.response?.data?.message || e.message));
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
    <div class="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">

      <div class="bg-slate-950 p-4 flex justify-between items-center border-b border-slate-800">
        <div>
          <h2 class="text-lg font-bold text-white">Прийом пацієнта</h2>
          <p class="text-sm text-slate-400">
            {{ patientName }}
            <span v-if="!patientId" class="text-red-400 text-xs ml-2">(Гість, не зареєстрований)</span>
          </p>
        </div>
        <button @click="$emit('close')" class="text-slate-400 hover:text-white text-2xl leading-none transition-colors">×</button>
      </div>

      <div class="p-6 overflow-y-auto custom-scrollbar space-y-4">

        <div v-if="status === 'done'" class="bg-emerald-900/30 text-emerald-400 border border-emerald-500/30 p-4 rounded-lg text-center font-bold">
          ✅ Цей візит вже завершено
        </div>

        <div v-else-if="!patientId" class="bg-amber-900/30 text-amber-400 border border-amber-500/30 p-4 rounded-lg text-sm">
          ⚠️ Цей запис створено вручну без прив'язки до картки пацієнта.
          <br>Щоб внести медичні записи, спершу створіть пацієнта в базі.
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Зуб № (опціонально)</label>
              <input v-model="form.tooth_number" type="number" placeholder="Напр. 46"
                     class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white placeholder-slate-600 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-all">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Новий статус зуба</label>
              <select v-model="form.update_tooth_status"
                      class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white outline-none focus:border-emerald-500">
                <option value="">-- Не змінювати --</option>
                <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.label }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Скарги пацієнта</label>
            <textarea v-model="form.complaints" rows="2"
                      class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white placeholder-slate-600 focus:border-emerald-500 outline-none"
                      placeholder="На що скаржиться?"></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Діагноз <span class="text-red-500">*</span></label>
            <input v-model="form.diagnosis" type="text"
                   class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white placeholder-slate-600 focus:border-emerald-500 outline-none"
                   placeholder="Напр. Карієс дентину">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Проведене лікування <span class="text-red-500">*</span></label>
            <textarea v-model="form.treatment" rows="4"
                      class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white placeholder-slate-600 focus:border-emerald-500 outline-none"
                      placeholder="Опишіть маніпуляції..."></textarea>
          </div>
        </div>

      </div>

      <div class="p-4 border-t border-slate-800 bg-slate-950 flex justify-end gap-3">
        <button @click="$emit('close')" class="px-4 py-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">Закрити</button>

        <button
            v-if="status !== 'done' && patientId"
            @click="saveRecord"
            :disabled="loading"
            class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 disabled:opacity-50 font-medium shadow-lg shadow-emerald-500/20 transition-all"
        >
          {{ loading ? 'Збереження...' : 'Завершити прийом' }}
        </button>
      </div>
    </div>
  </div>
</template>