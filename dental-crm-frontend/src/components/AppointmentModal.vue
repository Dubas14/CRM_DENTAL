<script setup>
import { ref, computed } from 'vue';
import apiClient from '../services/apiClient';

const props = defineProps({
  appointment: Object,
  isOpen: Boolean
});

// Додаємо подію 'create-patient'
const emit = defineEmits(['close', 'saved', 'create-patient']);

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
  // Валідація зубів
  if (form.value.tooth_number) {
    const t = Number(form.value.tooth_number);
    const isValidAdult = t >= 11 && t <= 48;
    const isValidChild = t >= 51 && t <= 85;
    if (!isValidAdult && !isValidChild) {
      alert('Невірний номер зуба! Використовуйте ISO (11-48).');
      return;
    }
  }

  if (!patientId.value) {
    alert('Помилка: Цей запис не привʼязаний до пацієнта.');
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

      <!-- Заголовок -->
      <div class="bg-slate-950 p-4 flex justify-between items-center border-b border-slate-800">
        <div>
          <h2 class="text-lg font-bold text-white">Прийом пацієнта</h2>
          <p class="text-sm text-slate-400">
            {{ patientName }}
            <span v-if="!patientId" class="text-red-400 text-xs ml-2">(Гість)</span>
          </p>
        </div>
        <button @click="$emit('close')" class="text-slate-400 hover:text-white text-2xl leading-none transition-colors">×</button>
      </div>

      <!-- Тіло форми -->
      <div class="p-6 overflow-y-auto custom-scrollbar space-y-4">

        <div v-if="status === 'done'" class="bg-emerald-900/30 text-emerald-400 border border-emerald-500/30 p-4 rounded-lg text-center font-bold">
          ✅ Цей візит вже завершено
        </div>

        <!-- 🔥 ОСЬ ТУТ КНОПКА 🔥 -->
        <div v-else-if="!patientId" class="bg-amber-900/20 border border-amber-500/30 p-4 rounded-lg flex flex-col sm:flex-row items-center justify-between gap-4">
          <div class="text-amber-400 text-sm">
            <span class="font-bold block mb-1">⚠️ Пацієнт не ідентифікований</span>
            Цей запис не прив'язаний до анкети. Створіть анкету, щоб внести історію лікування.
          </div>
          <button
              @click="$emit('create-patient', patientName)"
              class="whitespace-nowrap px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-lg text-sm font-bold shadow-lg transition-colors"
          >
            + Створити анкету
          </button>
        </div>

        <!-- Форма лікування (показується тільки якщо є patientId) -->
        <div v-else class="space-y-4">

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Зуб №</label>
              <input v-model="form.tooth_number" type="number" placeholder="Напр. 46" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white outline-none focus:border-emerald-500 transition-colors">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-400 mb-1">Статус</label>
              <select v-model="form.update_tooth_status" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white outline-none focus:border-emerald-500 transition-colors">
                <option value="">-- Не змінювати --</option>
                <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Діагноз *</label>
            <input v-model="form.diagnosis" type="text" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white outline-none focus:border-emerald-500 transition-colors">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Скарги</label>
            <textarea v-model="form.complaints" rows="2" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white outline-none focus:border-emerald-500 transition-colors"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-400 mb-1">Лікування *</label>
            <textarea v-model="form.treatment" rows="3" class="w-full bg-slate-950 border border-slate-700 rounded-lg p-2 text-white outline-none focus:border-emerald-500 transition-colors"></textarea>
          </div>
        </div>

      </div>

      <!-- Футер -->
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