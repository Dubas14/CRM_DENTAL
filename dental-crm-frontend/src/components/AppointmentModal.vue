<script setup>
import { ref, computed } from 'vue';
import apiClient from '../services/apiClient';

const props = defineProps({
  appointment: Object, // Об'єкт запису з календаря
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

const patientName = computed(() => props.appointment?.extendedProps?.patient_name || 'Пацієнт');
const patientId = computed(() => props.appointment?.extendedProps?.patient_id);
const appointmentId = computed(() => props.appointment?.id);

const saveRecord = async () => {
  if (!form.value.diagnosis || !form.value.treatment) {
    alert('Заповніть діагноз та лікування');
    return;
  }

  loading.value = true;
  try {
    // Відправляємо запит на створення MedicalRecord
    // Бекенд сам закриє візит (status -> done)
    await apiClient.post(`/patients/${patientId.value}/records`, {
      ...form.value,
      appointment_id: appointmentId.value
    });

    alert('Прийом завершено успішно!');
    emit('saved'); // Сигнал батьківському компоненту оновити календар
    emit('close');
  } catch (e) {
    alert('Помилка: ' + (e.response?.data?.message || e.message));
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">

      <div class="bg-slate-900 p-4 flex justify-between items-center text-white">
        <div>
          <h2 class="text-lg font-bold">Прийом пацієнта</h2>
          <p class="text-sm text-slate-400">{{ patientName }}</p>
        </div>
        <button @click="$emit('close')" class="text-slate-400 hover:text-white">✕</button>
      </div>

      <div class="p-6 overflow-y-auto custom-scrollbar space-y-4">

        <div v-if="appointment?.extendedProps?.status === 'done'" class="bg-emerald-100 text-emerald-800 p-4 rounded-lg text-center font-bold">
          ✅ Цей візит вже завершено
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Зуб № (опціонально)</label>
              <input v-model="form.tooth_number" type="number" placeholder="Напр. 46" class="w-full border rounded-lg p-2 bg-slate-50">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Новий статус зуба</label>
              <select v-model="form.update_tooth_status" class="w-full border rounded-lg p-2 bg-slate-50">
                <option value="">-- Не змінювати --</option>
                <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.label }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Скарги пацієнта</label>
            <textarea v-model="form.complaints" rows="2" class="w-full border rounded-lg p-2 bg-slate-50" placeholder="На що скаржиться?"></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Діагноз <span class="text-red-500">*</span></label>
            <input v-model="form.diagnosis" type="text" class="w-full border rounded-lg p-2 bg-slate-50" placeholder="Напр. Карієс дентину">
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Проведене лікування <span class="text-red-500">*</span></label>
            <textarea v-model="form.treatment" rows="4" class="w-full border rounded-lg p-2 bg-slate-50" placeholder="Опишіть маніпуляції..."></textarea>
          </div>
        </div>

      </div>

      <div class="p-4 border-t bg-gray-50 flex justify-end gap-3">
        <button @click="$emit('close')" class="px-4 py-2 text-slate-600 hover:bg-gray-200 rounded-lg">Закрити</button>

        <button
            v-if="appointment?.extendedProps?.status !== 'done'"
            @click="saveRecord"
            :disabled="loading"
            class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 font-medium shadow-lg shadow-emerald-500/30"
        >
          {{ loading ? 'Збереження...' : 'Завершити прийом' }}
        </button>
      </div>
    </div>
  </div>
</template>