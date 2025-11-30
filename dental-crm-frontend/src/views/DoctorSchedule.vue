<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';
import { usePermissions } from '../composables/usePermissions';

const doctors = ref([]);
const selectedDoctorId = ref('');
const selectedDate = ref(new Date().toISOString().slice(0, 10));

const { user } = useAuth();
const { isDoctor } = usePermissions();
const doctorProfile = computed(() => user.value?.doctor || null);

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

const appointments = ref([]);
const loadingAppointments = ref(false);
const appointmentsError = ref(null);

const selectedDoctor = computed(() =>
    doctors.value.find((d) => d.id === Number(selectedDoctorId.value))
);

const route = useRoute();

const linkedPatientId = computed(() => {
  const raw = route.query.patient_id || route.query.patient;
  const num = Number(raw);
  return Number.isFinite(num) && num > 0 ? num : null;
});

const ensureOwnDoctorSelected = () => {
  if (isDoctor.value && doctorProfile.value?.id) {
    selectedDoctorId.value = String(doctorProfile.value.id);
  }
};

watch(
    () => doctorProfile.value?.id,
    () => {
      ensureOwnDoctorSelected();
    },
    { immediate: true },
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
const loadAppointments = async () => {
  if (!selectedDoctorId.value || !selectedDate.value) return;

  loadingAppointments.value = true;
  appointmentsError.value = null;

  try {
    const { data } = await apiClient.get(
        `/doctors/${selectedDoctorId.value}/appointments`,
        { params: { date: selectedDate.value } },
    );
    appointments.value = data;
  } catch (e) {
    console.error(e);
    appointmentsError.value =
        e.response?.data?.message || e.message || 'Не вдалося завантажити записи';
  } finally {
    loadingAppointments.value = false;
  }
};

const refreshScheduleData = async () => {
  await loadSlots();
  await loadAppointments();
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

  const trimmedName = bookingName.value.trim();
  const trimmedComment = bookingComment.value.trim();

  if (!trimmedName) {
    bookingError.value = 'Вкажіть ім’я або контакт пацієнта';
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
      patient_id: linkedPatientId.value,
      comment: `Пацієнт: ${trimmedName}${trimmedComment ? `. ${trimmedComment}` : ''}`,
      source: 'crm',
    });

    bookingSuccess.value = true;
    await refreshScheduleData();
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
  await refreshScheduleData();
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
      <div v-if="!isDoctor" class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">
          Лікар
        </span>
        <select
            v-model="selectedDoctorId"
            :disabled="loadingDoctors"
            class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm"
            @change="refreshScheduleData"
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

      <div v-else class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">
          Лікар
        </span>
        <div class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200">
          {{ doctorProfile?.full_name || selectedDoctor?.full_name || '—' }}
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">
          Дата
        </span>
        <input
            v-model="selectedDate"
            type="date"
            class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm"
            @change="refreshScheduleData"
        />
      </div>

      <button
          type="button"
          class="ml-auto px-3 py-2 rounded-lg border border-slate-700 text-sm hover:bg-slate-800"
          @change="refreshScheduleData"
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
                  :disabled="bookingLoading || !bookingName.trim()"
                  class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400 disabled:opacity-60"
                  @click="bookSelectedSlot"
              >
                {{ bookingLoading ? 'Створення...' : 'Створити запис' }}
              </button>
            </div>
          </div>
          <!-- заплановані записи на цей день -->
          <div class="mt-6 space-y-2">
            <div class="text-sm text-slate-400">
              Записи на {{ selectedDate }}:
            </div>

            <div v-if="loadingAppointments" class="text-slate-300 text-sm">
              Завантаження записів...
            </div>

            <div v-else-if="appointmentsError" class="text-red-400 text-sm">
              ❌ {{ appointmentsError }}
            </div>

            <div v-else-if="appointments.length === 0" class="text-slate-500 text-sm">
              На цю дату поки немає записів.
            </div>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/40"
            >
              <table class="min-w-full text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                <tr>
                  <th class="px-4 py-2 text-left">Час</th>
                  <th class="px-4 py-2 text-left">Пацієнт / коментар</th>
                  <th class="px-4 py-2 text-left">Статус</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="a in appointments"
                    :key="a.id"
                    class="border-t border-slate-800"
                >
                  <td class="px-4 py-2 text-slate-200">
                    {{ a.start_at }} <!-- можна потім відформатувати через dayjs -->
                  </td>
                  <td class="px-4 py-2 text-slate-100">
                    {{ a.patient_name || a.comment || '—' }}
                  </td>
                  <td class="px-4 py-2 text-slate-300">
                    {{ a.status || 'planned' }}
                  </td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
