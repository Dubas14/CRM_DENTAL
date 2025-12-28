<template>
  <div class="smart-slot-picker">
    <div v-if="!showList" class="flex items-center gap-2">
      <button
        type="button"
        @click="fetchSuggestions"
        :disabled="loading || !canSearch"
        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        <span v-if="loading" class="animate-spin">⏳</span>
        <span v-else>✨</span>
        Підібрати час
      </button>
      <span v-if="!canSearch" class="text-xs text-gray-500">
        (Оберіть лікаря)
      </span>
    </div>

    <div v-else class="mt-3 bg-gray-50 border border-gray-200 rounded-lg p-3">
      <div class="flex justify-between items-center mb-2">
        <h4 class="text-sm font-semibold text-gray-700">Рекомендовані вікна:</h4>
        <button @click="showList = false" class="text-gray-400 hover:text-gray-600">✕</button>
      </div>

      <div v-if="loading" class="text-center py-4 text-gray-500">
        Пошук ідеального часу...
      </div>

      <div v-else-if="slots.length === 0" class="text-center py-2 text-sm text-gray-500">
        Вільних вікон на найближчі дні не знайдено 😔
      </div>

      <ul v-else class="space-y-2">
        <li v-for="(slot, index) in slots" :key="index">
          <button
            type="button"
            @click="selectSlot(slot)"
            class="w-full flex items-center justify-between p-2 bg-white border border-gray-200 rounded hover:border-indigo-500 hover:ring-1 hover:ring-indigo-500 transition-all text-left group"
          >
            <div>
              <div class="font-medium text-gray-900">
                {{ formatDate(slot.date) }}
              </div>
              <div class="text-xs text-gray-500">
                {{ slot.start }} - {{ slot.end }}
              </div>
            </div>
            <div class="text-indigo-600 opacity-0 group-hover:opacity-100 font-medium text-sm">
              Обрати →
            </div>
          </button>
        </li>
      </ul>

      <div v-if="slots.length > 0" class="mt-2 text-xs text-gray-400 text-center">
        Враховано графік, кабінети та обладнання
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import calendarApi from '../services/calendarApi';

const props = defineProps({
  doctorId: { type: [Number, String], required: true },
  procedureId: { type: [Number, String], default: null },
  fromDate: { type: String, default: () => new Date().toISOString().split('T')[0] }
});

const emit = defineEmits(['select']);

const loading = ref(false);
const showList = ref(false);
const slots = ref([]);

const canSearch = computed(() => !!props.doctorId);

const fetchSuggestions = async () => {
  if (!canSearch.value) return;

  loading.value = true;
  showList.value = true;
  slots.value = [];

  try {
    const { data } = await calendarApi.getBookingSuggestions({
      doctor_id: props.doctorId,
      procedure_id: props.procedureId,
      from_date: props.fromDate,
      limit: 5
    });
    slots.value = data?.slots || [];
  } catch (e) {
    console.error('Помилка підбору слотів:', e);
    slots.value = [];
  } finally {
    loading.value = false;
  }
};

const selectSlot = (slot) => {
  emit('select', {
    date: slot.date,
    start: slot.start,
    end: slot.end
  });
  showList.value = false;
};

const formatDate = (dateStr) => {
  const date = new Date(dateStr);
  return new Intl.DateTimeFormat('uk-UA', {
    day: 'numeric',
    month: 'long',
    weekday: 'short'
  }).format(date);
};
</script>

<style scoped>
.smart-slot-picker {
  margin-top: 10px;
  margin-bottom: 10px;
}
</style>
