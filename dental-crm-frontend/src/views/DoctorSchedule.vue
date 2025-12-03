<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'; // Додано onUnmounted
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';
import { usePermissions } from '../composables/usePermissions';
import AppointmentModal from '../components/AppointmentModal.vue';
import PatientCreateModal from '../components/PatientCreateModal.vue';

const route = useRoute();
const router = useRouter();

// --- Модальні вікна ---
const showModal = ref(false);
const showCreatePatientModal = ref(false);
const selectedEvent = ref(null);

// ---- Стани розкладу ----
const doctors = ref([]);
const selectedDoctorId = ref('');
const selectedDate = ref(new Date().toISOString().slice(0, 10));

const loadingDoctors = ref(true);
const loadingSlots = ref(false);
const error = ref(null);
const slots = ref([]);
const slotsReason = ref(null);

const appointments = ref([]);
const loadingAppointments = ref(false);
const appointmentsError = ref(null);

// ---- Бронювання ----
const bookingSlot = ref(null);
const bookingLoading = ref(false);
const bookingError = ref(null);
const bookingSuccess = ref(false);

const bookingName = ref('');
const bookingPhone = ref('');
const bookingComment = ref('');
const selectedPatientForBooking = ref(null);

// Змінна для збереження ID запису, до якого треба прив'язати створеного пацієнта
const appointmentToLink = ref(null);

// Пошук
const searchResults = ref([]);
const isSearching = ref(false);
let searchTimeout = null;
let autoRefreshInterval = null; // Таймер для автооновлення

// ---- Auth ----
const { user } = useAuth();
const { isDoctor } = usePermissions();
const doctorProfile = computed(() => user.value?.doctor || null);

const canOpenWeeklySettings = computed(() => {
  if (!selectedDoctorId.value) return false;
  if (['super_admin', 'clinic_admin'].includes(user.value?.global_role)) return true;

  if (isDoctor.value && doctorProfile.value?.id) {
    return Number(selectedDoctorId.value) === Number(doctorProfile.value.id);
  }

  return false;
});

const linkedPatientId = computed(() => {
  const raw = route.query.patient_id || route.query.patient;
  const num = Number(raw);
  return Number.isFinite(num) && num > 0 ? num : null;
});

const selectedDoctor = computed(() =>
    doctors.value.find((d) => d.id === Number(selectedDoctorId.value)),
);

// Фіксація лікаря
const ensureOwnDoctorSelected = () => {
  if (isDoctor.value && doctorProfile.value?.id) {
    selectedDoctorId.value = String(doctorProfile.value.id);
  }
};

watch(() => doctorProfile.value?.id, () => { ensureOwnDoctorSelected(); }, { immediate: true });

// === ЛОГІКА ПОШУКУ ===
watch(bookingName, (newVal) => {
  if (selectedPatientForBooking.value && selectedPatientForBooking.value.full_name === newVal) {
    return;
  }
  if (selectedPatientForBooking.value && selectedPatientForBooking.value.full_name !== newVal) {
    selectedPatientForBooking.value = null;
  }

  clearTimeout(searchTimeout);

  if (!newVal || newVal.length < 2) {
    searchResults.value = [];
    return;
  }

  isSearching.value = true;
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await apiClient.get('/patients', { params: { search: newVal } });
      searchResults.value = data.data || [];
    } catch (e) {
      console.error(e);
    } finally {
      isSearching.value = false;
    }
  }, 300);
});

const selectPatientFromSearch = (patient) => {
  selectedPatientForBooking.value = patient;
  bookingName.value = patient.full_name;
  bookingPhone.value = patient.phone || '';
  searchResults.value = [];
};

// === РОБОТА З МОДАЛКАМИ ===

function openAppointment(appt) {
  selectedEvent.value = appt;
  showModal.value = true;
}

function onOpenCreatePatientFromModal(nameFromModal) {
  showModal.value = false;
  appointmentToLink.value = selectedEvent.value;
  bookingName.value = nameFromModal;
  bookingPhone.value = '';
  showCreatePatientModal.value = true;
}

async function onPatientCreated(newPatient) {
  if (appointmentToLink.value) {
    try {
      const apptId = appointmentToLink.value.id || (appointmentToLink.value.extendedProps && appointmentToLink.value.extendedProps.id);

      if (!apptId) throw new Error("Не знайдено ID запису");

      await apiClient.put(`/appointments/${apptId}`, {
        patient_id: newPatient.id
      });

      alert(`Пацієнт ${newPatient.full_name} створений і прив'язаний до запису!`);
      await refreshScheduleData();

      const updatedAppt = appointments.value.find(a => a.id === apptId);
      if (updatedAppt) {
        openAppointment(updatedAppt);
      }

    } catch (e) {
      alert('Пацієнта створено, але не вдалося прив\'язати до запису: ' + e.message);
    } finally {
      appointmentToLink.value = null;
    }
    return;
  }

  selectedPatientForBooking.value = newPatient;
  bookingName.value = newPatient.full_name;
  bookingPhone.value = newPatient.phone || '';
}

function onRecordSaved() {
  loadAppointments();
}

function clearBookingForm() {
  selectedPatientForBooking.value = null;
  appointmentToLink.value = null;
  bookingName.value = '';
  bookingPhone.value = '';
  bookingComment.value = '';
  bookingError.value = null;
  bookingSuccess.value = false;
  searchResults.value = [];
}

const openWeeklySettings = () => {
  if (!canOpenWeeklySettings.value) return;

  router.push({ name: 'doctor-weekly-schedule', params: { id: selectedDoctorId.value } });
};

// === ЗАВАНТАЖЕННЯ ДАНИХ ===

// Завантаження пацієнта, якщо ми прийшли з його картки
const loadLinkedPatient = async () => {
  if (!linkedPatientId.value) return;
  try {
    const { data } = await apiClient.get(`/patients/${linkedPatientId.value}`);
    preloadedPatient.value = data; // Зберігаємо для авто-вибору
  } catch (e) {
    console.error("Не вдалося завантажити дані пацієнта", e);
  }
};
// Змінна для авто-вибору
const preloadedPatient = ref(null);

const loadDoctors = async () => {
  loadingDoctors.value = true;
  try {
    const { data } = await apiClient.get('/doctors');
    doctors.value = data;
    if (isDoctor.value && doctorProfile.value?.id) {
      selectedDoctorId.value = String(doctorProfile.value.id);
    } else if (!selectedDoctorId.value && data.length > 0) {
      selectedDoctorId.value = String(data[0].id);
    }
  } catch (e) {
    error.value = 'Помилка завантаження лікарів';
  } finally {
    loadingDoctors.value = false;
  }
};

const loadSlots = async (silent = false) => {
  if (!selectedDoctorId.value || !selectedDate.value) return;

  if (!silent) loadingSlots.value = true;
  error.value = null;

  // Якщо ми просто оновлюємо фон, не скидаємо слоти, щоб не мигало
  if (!silent) {
    slots.value = [];
    slotsReason.value = null;
    bookingSlot.value = null;
  }

  try {
    const { data } = await apiClient.get(
        `/doctors/${selectedDoctorId.value}/slots`,
        { params: { date: selectedDate.value } },
    );
    slots.value = data.slots || [];
    slotsReason.value = data.reason || null;
  } catch (e) {
    if (!silent) error.value = 'Не вдалося завантажити слоти';
  } finally {
    if (!silent) loadingSlots.value = false;
  }
};

const loadAppointments = async (silent = false) => {
  if (!selectedDoctorId.value || !selectedDate.value) return;

  if (!silent) loadingAppointments.value = true;
  appointmentsError.value = null;

  try {
    const { data } = await apiClient.get(
        `/doctors/${selectedDoctorId.value}/appointments`,
        { params: { date: selectedDate.value } },
    );
    appointments.value = data;
  } catch (e) {
    console.error(e);
    if (!silent) appointmentsError.value = 'Не вдалося завантажити записи';
  } finally {
    if (!silent) loadingAppointments.value = false;
  }
};

const refreshScheduleData = async (silent = false) => {
  await loadSlots(silent);
  await loadAppointments(silent);
};

const selectSlot = (slot) => {
  bookingSlot.value = slot;
  clearBookingForm();

  // АВТО-ВИБІР: Якщо ми прийшли з картки пацієнта
  if (preloadedPatient.value) {
    selectedPatientForBooking.value = preloadedPatient.value;
    bookingName.value = preloadedPatient.value.full_name;
    bookingPhone.value = preloadedPatient.value.phone || '';
  }
};

const bookSelectedSlot = async () => {
  if (!bookingSlot.value || !selectedDoctorId.value || !selectedDate.value) return;

  const finalName = selectedPatientForBooking.value
      ? selectedPatientForBooking.value.full_name
      : bookingName.value.trim();

  if (!finalName) {
    bookingError.value = 'Вкажіть ім’я пацієнта';
    return;
  }

  let commentText = bookingComment.value.trim();
  if (!selectedPatientForBooking.value && bookingPhone.value) {
    commentText += ` (Тел: ${bookingPhone.value})`;
  }

  bookingLoading.value = true;
  try {
    await apiClient.post('/appointments', {
      doctor_id: Number(selectedDoctorId.value),
      date: selectedDate.value,
      time: bookingSlot.value.start,
      patient_id: selectedPatientForBooking.value?.id || linkedPatientId.value,
      comment: selectedPatientForBooking.value
          ? commentText
          : `Пацієнт: ${finalName}. ${commentText}`,
      source: 'crm',
    });
    bookingSuccess.value = true;
    await refreshScheduleData();
    setTimeout(() => { bookingSlot.value = null; }, 1500);
  } catch (e) {
    bookingError.value = e.response?.data?.message || 'Не вдалося створити запис';
  } finally {
    bookingLoading.value = false;
  }
};

const validatePhoneInput = (event) => {
  let val = event.target.value.replace(/[^0-9+\-() ]/g, '');
  bookingPhone.value = val;
  event.target.value = val;
};

// === LIFECYCLE ===
onMounted(async () => {
  await loadDoctors();
  await loadLinkedPatient();
  await refreshScheduleData();

  // Запускаємо авто-оновлення кожні 15 секунд
  autoRefreshInterval = setInterval(() => {
    // Оновлюємо тільки якщо не відкрито модальне вікно і не йде процес бронювання
    if (!showModal.value && !showCreatePatientModal.value && !bookingSlot.value) {
      refreshScheduleData(true); // true = silent mode (без спінера)
    }
  }, 15000);
});

onUnmounted(() => {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold">Розклад лікаря</h1>
        <p class="text-sm text-slate-400">Оберіть лікаря та дату для перегляду.</p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Індикатор автооновлення (опціонально) -->
        <div class="text-xs text-slate-600 animate-pulse">
          ● Дані оновлюються автоматично
        </div>

        <button
            v-if="canOpenWeeklySettings"
            type="button"
            class="px-3 py-2 rounded-lg border border-slate-700 text-sm text-slate-200 hover:bg-slate-800"
            @click="openWeeklySettings"
        >
          Налаштувати тижневий розклад
        </button>
      </div>
    </div>

    <!-- Вибір лікаря -->
    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4">
      <div v-if="!isDoctor" class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">Лікар</span>
        <select v-model="selectedDoctorId" class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm" @change="refreshScheduleData">
          <option value="" disabled>Оберіть лікаря</option>
          <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">
            {{ doctor.full_name }}
          </option>
        </select>
      </div>
      <div v-else class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">Лікар</span>
        <div class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200">
          {{ doctorProfile?.full_name || selectedDoctor?.full_name || '—' }}
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <span class="text-xs uppercase tracking-wide text-slate-400">Дата</span>
        <input v-model="selectedDate" type="date" class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm" @change="refreshScheduleData" />
      </div>

      <button type="button" class="ml-auto px-3 py-2 rounded-lg border border-slate-700 text-sm hover:bg-slate-800" @click="refreshScheduleData">
        Оновити
      </button>
    </div>

    <!-- Контент -->
    <div v-if="error" class="text-red-400">❌ {{ error }}</div>
    <div v-else class="space-y-3">

      <!-- Слоти -->
      <div v-if="loadingSlots" class="text-slate-300">Завантаження слотів...</div>
      <div v-else>
        <div v-if="slots.length === 0" class="text-slate-400 text-sm">Немає вільних слотів.</div>
        <div v-else class="flex flex-wrap gap-2">
          <button v-for="slot in slots" :key="slot.start"
                  class="px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/40 text-emerald-200 text-sm hover:bg-emerald-500/20"
                  @click="selectSlot(slot)">
            {{ slot.start }} – {{ slot.end }}
          </button>
        </div>

        <!-- ФОРМА БРОНЮВАННЯ -->
        <div v-if="bookingSlot" class="mt-4 rounded-xl border border-slate-700 bg-slate-900 shadow-xl p-5 space-y-4 relative">
          <div class="flex justify-between items-center">
            <h3 class="text-emerald-400 font-bold text-lg">Запис на {{ bookingSlot.start }}</h3>
            <button @click="bookingSlot = null" class="text-slate-500 hover:text-white">✕</button>
          </div>

          <div v-if="bookingSuccess" class="bg-emerald-900/30 text-emerald-400 p-3 rounded border border-emerald-500/30">
            ✅ Запис успішно створено!
          </div>
          <div v-if="bookingError" class="bg-red-900/30 text-red-400 p-3 rounded border border-red-500/30">
            {{ bookingError }}
          </div>

          <!-- Якщо пацієнт вже обраний (із бази або з попередньої сторінки) -->
          <div v-if="selectedPatientForBooking" class="flex items-center justify-between bg-blue-900/20 border border-blue-500/30 p-3 rounded-lg">
            <div>
              <span class="block text-xs text-blue-400 uppercase font-bold mb-1">Обраний пацієнт</span>
              <div class="text-white text-lg font-bold">{{ selectedPatientForBooking.full_name }}</div>
              <div class="text-slate-400 text-sm">{{ selectedPatientForBooking.phone }}</div>
            </div>
            <button @click="selectedPatientForBooking = null; bookingName = ''" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded border border-slate-600 transition-colors">
              Змінити
            </button>
          </div>

          <!-- Форма пошуку -->
          <div v-else class="grid md:grid-cols-2 gap-4 relative">
            <div class="relative">
              <label class="block text-xs text-slate-400 mb-1 uppercase">Пошук пацієнта (Ім'я або Телефон)</label>
              <input
                  v-model="bookingName"
                  type="text"
                  placeholder="Почніть вводити..."
                  class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
              />

              <div v-if="searchResults.length > 0 && !selectedPatientForBooking"
                   class="absolute z-50 w-full bg-slate-800 border border-slate-600 rounded-lg shadow-xl mt-1 max-h-48 overflow-y-auto">
                <ul>
                  <li v-for="p in searchResults" :key="p.id"
                      @click="selectPatientFromSearch(p)"
                      class="px-3 py-2 hover:bg-slate-700 cursor-pointer text-sm text-slate-200 border-b border-slate-700 last:border-0">
                    <div class="font-bold text-emerald-400">{{ p.full_name }}</div>
                    <div class="text-xs text-slate-400">{{ p.phone }} | {{ p.birth_date }}</div>
                  </li>
                </ul>
              </div>

              <!-- КНОПКА СТВОРЕННЯ -->
              <div v-if="bookingName.length > 2 && !selectedPatientForBooking" class="mt-2">
                <button
                    @click="showCreatePatientModal = true"
                    class="w-full flex items-center justify-center gap-2 text-xs bg-slate-800 hover:bg-emerald-900/30 text-emerald-400 border border-emerald-500/30 border-dashed px-3 py-2 rounded transition-all font-semibold"
                >
                  <span>+</span> Створити нову анкету для "{{ bookingName }}"
                </button>
              </div>
            </div>

            <div>
              <label class="block text-xs text-slate-400 mb-1 uppercase">Телефон (для гостя)</label>
              <input v-model="bookingPhone" @input="validatePhoneInput" type="text" placeholder="+380..." class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-white" />
            </div>
          </div>

          <div>
            <label class="block text-xs text-slate-400 mb-1 uppercase">Коментар</label>
            <textarea v-model="bookingComment" placeholder="Скарги, деталі..." class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-white h-20"></textarea>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button @click="bookingSlot = null" class="text-slate-400 hover:text-white px-3 py-2 text-sm">Скасувати</button>
            <button @click="bookSelectedSlot" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2 rounded font-medium shadow-lg shadow-emerald-900/50">
              Підтвердити запис
            </button>
          </div>
        </div>

        <!-- Таблиця записів -->
        <div class="mt-8 space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-slate-400 text-xs font-bold uppercase tracking-wider">Записи на цей день</h3>
            <span v-if="loadingAppointments" class="text-xs text-slate-500 animate-pulse">Оновлення...</span>
          </div>

          <div v-if="appointments.length === 0" class="text-slate-500 text-sm italic">Немає записів.</div>
          <div v-else class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900/40">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-900/80 text-slate-400 text-xs uppercase">
              <tr>
                <th class="px-4 py-3 text-left font-medium">Час</th>
                <th class="px-4 py-3 text-left font-medium">Пацієнт</th>
                <th class="px-4 py-3 text-left font-medium">Статус</th>
              </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
              <tr v-for="a in appointments" :key="a.id"
                  @click="openAppointment(a)"
                  class="hover:bg-slate-800/50 cursor-pointer transition-colors group">
                <td class="px-4 py-3 text-emerald-400 font-bold font-mono">
                  {{ a.start_at ? a.start_at.slice(11, 16) : '' }}
                </td>
                <td class="px-4 py-3 text-slate-200">
                  <div class="font-medium">{{ a.patient_name || a.comment || '—' }}</div>
                  <div v-if="a.patient_id" class="text-[10px] text-blue-400 inline-flex items-center gap-1 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> АНКЕТА Є
                  </div>
                  <div v-else class="text-[10px] text-amber-500 inline-flex items-center gap-1 mt-0.5">
                    ⚠️ Гість
                  </div>
                </td>
                <td class="px-4 py-3">
                       <span v-if="a.status === 'done'" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                         Виконано
                       </span>
                  <span v-else class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-700/50 text-slate-300 border border-slate-600">
                         Заплановано
                       </span>
                </td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- МОДАЛКИ -->
        <AppointmentModal
            :is-open="showModal"
            :appointment="selectedEvent"
            @close="showModal = false"
            @saved="onRecordSaved"
            @create-patient="onOpenCreatePatientFromModal"
        />

        <PatientCreateModal
            :is-open="showCreatePatientModal"
            :initial-name="bookingName"
            :initial-phone="bookingPhone"
            @close="showCreatePatientModal = false"
            @created="onPatientCreated"
        />

      </div>
    </div>
  </div>
</template>