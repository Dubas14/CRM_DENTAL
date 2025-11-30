<script setup>
import { ref, onMounted, computed } from 'vue';
import apiClient from '../services/apiClient';

const doctors = ref([]);
const selectedDoctorId = ref('');
const selectedDate = ref(new Date().toISOString().slice(0, 10));

const loadingDoctors = ref(true);
const loadingSlots = ref(false);
const error = ref(null);
const slots = ref([]);
const slotsReason = ref(null);

// стан бронювання
const bookingSlot = ref(null); // { start, end }
const bookingLoading = ref(false);
const bookingError = ref(null);
const bookingSuccess = ref(false);
const bookingName = ref('');
const bookingComment = ref('');

const selectedDoctor = computed(() =>
    doctors.value.find((d) => d.id === Number(selectedDoctorId.value))
);

const loadDoctors = async () => {
  loadingDoctors.value = true;
  try {
    const { data } = await apiClient.get('/doctors');
    doctors.value = data;
    if (!selectedDoctorId.value && data.length > 0) {
      selectedDoctorId.value = String(data[0].id);
    }
  } catch (e) {
    console.error(e);
    error.value =
        e.response?.data?.message || e.message || 'Помилка завантаження лікарів';
  } finally {
    loadingDoctors.value = false;
  }
};

const loadSlots = async () => {
  if (!selectedDoctorId.value || !selectedDate.value) return;

  loadingSlots.value = true;
  error.value = null;
  slots.value = [];
  slotsReason.value = null;
  bookingSlot.value = null;
  bookingSuccess.value = false;
  bookingError.value = null;

  try {
    const { data } = await apiClient.get(
        `/doctors/${selectedDoctorId.value}/slots`,
        { params: { date: selectedDate.value } },
    );
    slots.value = data.slots || [];
    slotsReason.value = data.reason || null;
  } catch (e) {
    console.error(e);
    error.value =
        e.response?.data?.message ||
        e.message ||
        'Не вдалося завантажити слоти';
  } finally {
    loadingSlots.value = false;
  }
};

const selectSlot = (slot) => {
  bookingSlot.value = slot;
  bookingName.value = '';
  bookingComment.value = '';
  bookingError.value = null;
  bookingSuccess.value = false;
};

const bookSelectedSlot = async () => {
  if (!bookingSlot.value || !selectedDoctorId.value || !selectedDate.value) {
    return;
  }

  bookingLoading.value = true;
  bookingError.value = null;
  bookingSuccess.value = false;

  try {
    await apiClient.post('/appointments', {
      doctor_id: Number(selectedDoctorId.value),
      date: selectedDate.value,
      time: bookingSlot.value.start,
      // поки що пацієнтів нема – кладемо ім’я/телефон в коментар
      comment: bookingName.value
          ? `Пацієнт: ${bookingName.value}. ${bookingComment.value || ''}`
          : bookingComment.value || null,
      source: 'crm',
    });

    bookingSuccess.value = true;

    // перезавантажуємо слоти, щоб забрало зайнятий
    await loadSlots();
  } catch (e) {
    console.error(e);
    bookingError.value =
        e.response?.data?.message ||
        e.message ||
        'Не вдалося створити запис';
  } finally {
    bookingLoading.value = false;
  }
};

onMounted(async () => {
  await loadDoctors();
  await loadSlots();
});
</script>


<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Розклад лікаря</h1>
        <p class="text-sm text-slate-400">
          Обери лікаря та дату, щоб подивитись доступні слоти для запису.
        </p>
      </div>
    </div>

    <!-- вибір лікаря і дати -->
    <div
        class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4"
    >
      <div class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">
          Лікар
        </span>
        <select
            v-model="selectedDoctorId"
            :disabled="loadingDoctors"
            class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm"
            @change="loadSlots"
        >
          <option value="" disabled>Оберіть лікаря</option>
          <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
            {{ doctor.full_name }}
            <span v-if="doctor.specialization">
              ({{ doctor.specialization }})
            </span>
          </option>
        </select>
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">
          Дата
        </span>
        <input
            v-model="selectedDate"
            type="date"
            class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm"
            @change="loadSlots"
        />
      </div>

      <button
          type="button"
          class="ml-auto px-3 py-2 rounded-lg border border-slate-700 text-sm hover:bg-slate-800"
          @click="loadSlots"
      >
        Оновити
      </button>
    </div>

    <!-- помилки -->
    <div v-if="error" class="text-red-400">
      ❌ {{ error }}
    </div>

    <!-- слоти -->
    <div v-else class="space-y-3">
      <div class="text-sm text-slate-300">
        <span class="font-semibold">Обраний лікар:</span>
        <span v-if="selectedDoctor">
          {{ selectedDoctor.full_name }}
          <span v-if="selectedDoctor.specialization">
            ({{ selectedDoctor.specialization }})
          </span>
        </span>
        <span v-else class="text-slate-500">не обрано</span>
      </div>

      <div v-if="loadingSlots" class="text-slate-300">
        Завантаження слотів...
      </div>

      <div v-else>
        <div v-if="slots.length === 0" class="text-slate-400 text-sm">
          Немає вільних слотів на цю дату.
          <span v-if="slotsReason === 'day_off'"> Лікар у вихідний.</span>
          <span v-else-if="slotsReason === 'no_schedule'">
            Розклад для цього дня не налаштований.
          </span>
        </div>

        <div v-else class="space-y-4">
          <div class="text-sm text-slate-400">
            Вільні слоти на {{ selectedDate }}:
          </div>
          <div class="flex flex-wrap gap-2">
            <button
                v-for="slot in slots"
                :key="slot.start"
                type="button"
                class="px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/40 text-emerald-200 text-sm hover:bg-emerald-500/20"
                @click="selectSlot(slot)"
            >
              {{ slot.start }} – {{ slot.end }}
            </button>
          </div>

          <!-- форма бронювання -->
          <div
              v-if="bookingSlot"
              class="mt-2 rounded-xl border border-slate-800 bg-slate-900/70 p-4 space-y-3"
          >
            <div class="text-sm text-slate-200">
              Створення запису на
              <span class="font-semibold">
        {{ bookingSlot.start }}–{{ bookingSlot.end }}
      </span>
              ({{ selectedDate }})
            </div>

            <div v-if="bookingError" class="text-sm text-red-400">
              ❌ {{ bookingError }}
            </div>

            <div v-if="bookingSuccess" class="text-sm text-emerald-400">
              ✅ Запис створено
            </div>

            <div class="grid gap-3 md:grid-cols-2">
              <div class="md:col-span-2">
                <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">
                  Ім’я/контакт пацієнта (тимчасово, поки немає модуля пацієнтів)
                </label>
                <input
                    v-model="bookingName"
                    type="text"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm"
                    placeholder="Напр.: Петренко О., +380..."
                />
              </div>

              <div class="md:col-span-2">
                <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">
                  Коментар
                </label>
                <textarea
                    v-model="bookingComment"
                    rows="2"
                    class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm"
                    placeholder="Скарги, побажання, додаткові деталі..."
                ></textarea>
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <button
                  type="button"
                  class="px-3 py-2 rounded-lg border border-slate-700 text-sm text-slate-300 hover:bg-slate-800"
                  @click="bookingSlot = null"
              >
                Скасувати
              </button>
              <button
                  type="button"
                  :disabled="bookingLoading"
                  class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400 disabled:opacity-60"
                  @click="bookSelectedSlot"
              >
                {{ bookingLoading ? 'Створення...' : 'Створити запис' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
