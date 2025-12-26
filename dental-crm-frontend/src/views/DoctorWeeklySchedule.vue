<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../services/apiClient';

const route = useRoute();
const router = useRouter();

const doctorId = computed(() => Number(route.params.id));

const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const saveError = ref(null);

const days = ref([]);

const weekdayLabels = {
  1: 'Пн',
  2: 'Вт',
  3: 'Ср',
  4: 'Чт',
  5: 'Пт',
  6: 'Сб',
  7: 'Нд',
};

const buildDefaultWeek = (allWorking = false) => (
    Array.from({ length: 7 }, (_, idx) => {
      const weekday = idx + 1;
      return {
        weekday,
        is_working: allWorking ? [1, 2, 3, 4, 5].includes(weekday) : false,
        start_time: '09:00',
        end_time: '17:00',
        break_start: '13:00',
        break_end: '14:00',
        slot_duration_minutes: 30,
      };
    })
);

const mergeWithDefaults = (data) => {
  const hasApiData = Array.isArray(data) && data.length > 0;
  const defaults = buildDefaultWeek(!hasApiData);

  const map = new Map(
      (data || []).map(d => [d.weekday, {
        weekday: d.weekday,
        is_working: d.is_working ?? true,
        start_time: d.start_time?.slice(0,5),
        end_time:   d.end_time?.slice(0,5),
        break_start: d.break_start?.slice(0,5),
        break_end:   d.break_end?.slice(0,5),
        slot_duration_minutes: d.slot_duration_minutes,
      }])
  );

  return defaults.map((day) => {
    const fromApi = map.get(day.weekday);
    if (!fromApi) return day;

    return {
      ...day,
      ...fromApi,
      is_working: !!fromApi.is_working,
      start_time: fromApi.start_time || day.start_time,
      end_time: fromApi.end_time || day.end_time,
      break_start: fromApi.break_start || day.break_start,
      break_end: fromApi.break_end || day.break_end,
      slot_duration_minutes: fromApi.slot_duration_minutes || day.slot_duration_minutes,
    };
  });
};

const loadSchedule = async () => {
  loading.value = true;
  error.value = null;
  saveError.value = null;
  try {
    const { data } = await apiClient.get(`/doctors/${doctorId.value}/weekly-schedule`);
    days.value = mergeWithDefaults(data);
  } catch (e) {
    console.error(e);
    error.value = e.response?.data?.message || 'Не вдалося завантажити розклад';
  } finally {
    loading.value = false;
  }
};

const saveSchedule = async () => {
  saving.value = true;
  saveError.value = null;
  try {
    const payload = {
      days: days.value.map(d => ({
        ...d,
        start_time: d.is_working ? d.start_time : null,
        end_time:   d.is_working ? d.end_time   : null,
        break_start: d.is_working ? d.break_start : null,
        break_end:   d.is_working ? d.break_end   : null,
      })),
    };
    await apiClient.put(`/doctors/${doctorId.value}/weekly-schedule`, payload);
    router.push({ name: 'schedule', query: { doctor: doctorId.value } });
  } catch (e) {
    console.error(e);
    if (e.response?.data?.errors) {
      const firstKey = Object.keys(e.response.data.errors)[0];
      saveError.value = e.response.data.errors[firstKey][0];
    } else {
      saveError.value = e.response?.data?.message || 'Помилка збереження';
    }
  } finally {
    saving.value = false;
  }
};

onMounted(loadSchedule);
</script>

<template>
  <div class="space-y-6">
    <button
        type="button"
        class="text-xs text-text/70 hover:text-text/90"
        @click="$router.back()"
    >
      ← Назад
    </button>

    <div>
      <h1 class="text-2xl font-semibold">Тижневий розклад лікаря</h1>
      <p class="text-sm text-text/70">
        Вкажіть робочі дні та години. На основі цього будуть будуватися вільні слоти.
      </p>
    </div>

    <div v-if="loading" class="text-sm text-text/70">
      Завантаження...
    </div>

    <div v-else-if="error" class="text-sm text-red-400">
      ❌ {{ error }}
    </div>

    <div v-else class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4">
      <table class="min-w-full text-sm">
        <thead class="text-text/70 border-b border-border">
        <tr>
          <th class="px-3 py-2 text-left">День</th>
          <th class="px-3 py-2 text-left">Працює</th>
          <th class="px-3 py-2 text-left">З</th>
          <th class="px-3 py-2 text-left">До</th>
          <th class="px-3 py-2 text-left">Обід з</th>
          <th class="px-3 py-2 text-left">Обід до</th>
          <th class="px-3 py-2 text-left">Тривалість слота (хв)</th>
        </tr>
        </thead>
        <tbody>
        <tr
            v-for="day in days"
            :key="day.weekday"
            class="border-t border-border"
        >
          <td class="px-3 py-2">
            {{ weekdayLabels[day.weekday] }}
          </td>
          <td class="px-3 py-2">
            <input
                v-model="day.is_working"
                type="checkbox"
                class="h-4 w-4 rounded border-border/70 bg-card"
            />
          </td>
          <td class="px-3 py-2">
            <input
                v-model="day.start_time"
                :disabled="!day.is_working"
                type="time"
                class="rounded bg-bg border border-border/80 px-2 py-1 text-sm text-text disabled:opacity-60"
            />
          </td>
          <td class="px-3 py-2">
            <input
                v-model="day.end_time"
                :disabled="!day.is_working"
                type="time"
                class="rounded bg-bg border border-border/80 px-2 py-1 text-sm text-text disabled:opacity-60"
            />
          </td>
          <td class="px-3 py-2">
            <input
                v-model="day.break_start"
                :disabled="!day.is_working"
                type="time"
                class="rounded bg-bg border border-border/80 px-2 py-1 text-sm text-text disabled:opacity-60"
            />
          </td>
          <td class="px-3 py-2">
            <input
                v-model="day.break_end"
                :disabled="!day.is_working"
                type="time"
                class="rounded bg-bg border border-border/80 px-2 py-1 text-sm text-text disabled:opacity-60"
            />
          </td>
          <td class="px-3 py-2">
            <input
                v-model.number="day.slot_duration_minutes"
                type="number"
                min="5"
                max="240"
                class="w-20 rounded bg-bg border border-border/80 px-2 py-1 text-sm text-text"
            />
          </td>
        </tr>
        </tbody>
      </table>

      <div v-if="saveError" class="text-sm text-red-400">
        ❌ {{ saveError }}
      </div>

      <div class="flex justify-end gap-2">
        <button
            type="button"
            class="px-3 py-2 rounded-lg border border-border/80 text-sm text-text/80 hover:bg-card/80"
            @click="loadSchedule"
        >
          Скасувати
        </button>
        <button
            type="button"
            :disabled="saving"
            class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-text hover:bg-emerald-400 disabled:opacity-60"
            @click="saveSchedule"
        >
          {{ saving ? 'Збереження...' : 'Зберегти' }}
        </button>
      </div>
    </div>
  </div>
</template>
