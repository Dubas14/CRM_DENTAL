<script setup>
import { ref, onMounted, computed } from 'vue';
import apiClient from '../services/apiClient';

const props = defineProps({
  patientId: { type: Number, required: true }
});

const teethMap = ref({}); // Зберігаємо стан зубів { "18": "healthy", "21": "caries" }
const loading = ref(false);
const selectedTooth = ref(null); // Який зуб зараз редагуємо

// Генерація номерів зубів (Дорослі)
// Верхня щелепа: Q1 (18-11), Q2 (21-28)
// Нижня щелепа: Q4 (48-41), Q3 (31-38)
const quadrants = {
  q1: [18, 17, 16, 15, 14, 13, 12, 11],
  q2: [21, 22, 23, 24, 25, 26, 27, 28],
  q4: [48, 47, 46, 45, 44, 43, 42, 41],
  q3: [31, 32, 33, 34, 35, 36, 37, 38],
};

// Доступні статуси
const statuses = [
  { id: 'healthy', label: 'Здоровий', color: 'bg-white border-gray-300', fill: '#ffffff' },
  { id: 'caries', label: 'Карієс', color: 'bg-amber-100 border-amber-500 text-amber-700', fill: '#fcd34d' },
  { id: 'pulpitis', label: 'Пульпіт', color: 'bg-red-100 border-red-500 text-red-700', fill: '#ef4444' },
  { id: 'filled', label: 'Пломба', color: 'bg-blue-100 border-blue-500 text-blue-700', fill: '#3b82f6' },
  { id: 'missing', label: 'Відсутній', color: 'bg-gray-800 border-gray-900 text-gray-300', fill: '#374151' },
  { id: 'crown', label: 'Коронка', color: 'bg-yellow-100 border-yellow-600 text-yellow-800', fill: '#eab308' },
];

// Завантаження даних
const loadMap = async () => {
  loading.value = true;
  try {
    const { data } = await apiClient.get(`/patients/${props.patientId}/dental-map`);
    // Перетворюємо масив об'єктів у зручний об'єкт { "18": "status" }
    teethMap.value = data.reduce((acc, item) => {
      acc[item.tooth_number] = item.status;
      return acc;
    }, {});
  } catch (e) {
    console.error("Помилка завантаження карти:", e);
  } finally {
    loading.value = false;
  }
};

// Збереження статусу
const setStatus = async (statusId) => {
  if (!selectedTooth.value) return;

  const toothNum = selectedTooth.value;
  // Оптимістичне оновлення інтерфейсу
  teethMap.value[toothNum] = statusId;
  selectedTooth.value = null; // Закрити меню

  try {
    await apiClient.post(`/patients/${props.patientId}/dental-map`, {
      tooth_number: toothNum,
      status: statusId
    });
  } catch (e) {
    alert('Не вдалося зберегти статус зуба');
    loadMap(); // Відкат змін
  }
};

// Отримати колір для SVG
const getFillColor = (toothNum) => {
  const statusId = teethMap.value[toothNum] || 'healthy';
  const status = statuses.find(s => s.id === statusId);
  return status ? status.fill : '#ffffff';
};

onMounted(loadMap);
</script>

<template>
  <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
    <h3 class="text-lg font-semibold text-slate-700 mb-4">Зубна формула</h3>

    <div v-if="loading" class="text-center py-10 text-slate-400">Завантаження карти...</div>

    <div v-else class="relative">
      <div class="flex flex-col gap-8 items-center overflow-x-auto pb-4">

        <div class="flex gap-4">
          <div class="flex gap-1">
            <div v-for="t in quadrants.q1" :key="t" @click="selectedTooth = t"
                 class="cursor-pointer flex flex-col items-center group">
              <span class="text-xs text-slate-400 mb-1">{{ t }}</span>
              <svg width="40" height="50" viewBox="0 0 40 50" class="transition-transform group-hover:scale-110">
                <path d="M5,15 Q5,0 20,0 Q35,0 35,15 L35,40 Q35,50 20,50 Q5,50 5,40 Z"
                      :fill="getFillColor(t)" stroke="#94a3b8" stroke-width="2" />
                <path v-if="teethMap[t] === 'missing'" d="M10,10 L30,40 M30,10 L10,40" stroke="white" stroke-width="3"/>
              </svg>
            </div>
          </div>
          <div class="w-px bg-slate-300 mx-2"></div> <div class="flex gap-1">
          <div v-for="t in quadrants.q2" :key="t" @click="selectedTooth = t"
               class="cursor-pointer flex flex-col items-center group">
            <span class="text-xs text-slate-400 mb-1">{{ t }}</span>
            <svg width="40" height="50" viewBox="0 0 40 50" class="transition-transform group-hover:scale-110">
              <path d="M5,15 Q5,0 20,0 Q35,0 35,15 L35,40 Q35,50 20,50 Q5,50 5,40 Z"
                    :fill="getFillColor(t)" stroke="#94a3b8" stroke-width="2" />
              <path v-if="teethMap[t] === 'missing'" d="M10,10 L30,40 M30,10 L10,40" stroke="white" stroke-width="3"/>
            </svg>
          </div>
        </div>
        </div>

        <div class="flex gap-4">
          <div class="flex gap-1">
            <div v-for="t in quadrants.q4" :key="t" @click="selectedTooth = t"
                 class="cursor-pointer flex flex-col items-center group">
              <svg width="40" height="50" viewBox="0 0 40 50" class="transition-transform group-hover:scale-110">
                <path d="M5,35 Q5,50 20,50 Q35,50 35,35 L35,10 Q35,0 20,0 Q5,0 5,10 Z"
                      :fill="getFillColor(t)" stroke="#94a3b8" stroke-width="2" />
                <path v-if="teethMap[t] === 'missing'" d="M10,10 L30,40 M30,10 L10,40" stroke="white" stroke-width="3"/>
              </svg>
              <span class="text-xs text-slate-400 mt-1">{{ t }}</span>
            </div>
          </div>
          <div class="w-px bg-slate-300 mx-2"></div> <div class="flex gap-1">
          <div v-for="t in quadrants.q3" :key="t" @click="selectedTooth = t"
               class="cursor-pointer flex flex-col items-center group">
            <svg width="40" height="50" viewBox="0 0 40 50" class="transition-transform group-hover:scale-110">
              <path d="M5,35 Q5,50 20,50 Q35,50 35,35 L35,10 Q35,0 20,0 Q5,0 5,10 Z"
                    :fill="getFillColor(t)" stroke="#94a3b8" stroke-width="2" />
              <path v-if="teethMap[t] === 'missing'" d="M10,10 L30,40 M30,10 L10,40" stroke="white" stroke-width="3"/>
            </svg>
            <span class="text-xs text-slate-400 mt-1">{{ t }}</span>
          </div>
        </div>
        </div>
      </div>

      <div class="mt-8 flex flex-wrap gap-3 justify-center border-t border-slate-100 pt-4">
        <div v-for="s in statuses" :key="s.id" class="flex items-center gap-2">
          <div class="w-4 h-4 rounded border" :style="{ backgroundColor: s.fill, borderColor: s.id === 'healthy' ? '#cbd5e1' : s.fill }"></div>
          <span class="text-sm text-slate-600">{{ s.label }}</span>
        </div>
      </div>

      <div v-if="selectedTooth" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="selectedTooth = null">
        <div class="bg-white rounded-xl shadow-xl p-6 w-80 max-w-full">
          <h3 class="text-lg font-bold text-slate-800 mb-4">Зуб № {{ selectedTooth }}</h3>
          <p class="text-sm text-slate-500 mb-4">Оберіть поточний стан:</p>

          <div class="grid grid-cols-2 gap-2">
            <button v-for="s in statuses" :key="s.id"
                    @click="setStatus(s.id)"
                    class="px-3 py-2 rounded border text-sm font-medium transition-colors"
                    :class="s.color"
            >
              {{ s.label }}
            </button>
          </div>

          <button @click="selectedTooth = null" class="mt-4 w-full py-2 text-slate-400 text-sm hover:text-slate-600">
            Скасувати
          </button>
        </div>
      </div>

    </div>
  </div>
</template>