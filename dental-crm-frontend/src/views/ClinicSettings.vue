<script setup>
import { ref, onMounted, watch } from 'vue';
import clinicApi from '../services/clinicApi';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

const clinics = ref([]);
const selectedClinicId = ref('');

const rooms = ref([]);
const workingHours = ref([]);

const loadingRooms = ref(false);
const loadingHours = ref(false);
const savingHours = ref(false);
const errorRooms = ref(null);
const errorHours = ref(null);

const roomForm = ref({
  name: '',
  equipment: '',
  notes: '',
  is_active: true,
});

const weekdays = [
  { id: 1, label: 'Пн' },
  { id: 2, label: 'Вт' },
  { id: 3, label: 'Ср' },
  { id: 4, label: 'Чт' },
  { id: 5, label: 'Пт' },
  { id: 6, label: 'Сб' },
  { id: 7, label: 'Нд' },
];

const normalizeTime = (value) => (value ? value.slice(0, 5) : '');

const loadClinics = async () => {
  if (user.value?.global_role === 'super_admin') {
    const { data } = await clinicApi.list();
    clinics.value = data.data ?? data;
  } else {
    const { data } = await clinicApi.listMine();
    clinics.value = (data.clinics ?? []).map((clinic) => ({
      id: clinic.clinic_id,
      name: clinic.clinic_name,
    }));
  }

  if (!selectedClinicId.value && clinics.value.length) {
    selectedClinicId.value = clinics.value[0].id;
  }
};

const loadRooms = async () => {
  if (!selectedClinicId.value) return;
  loadingRooms.value = true;
  errorRooms.value = null;
  try {
    const { data } = await apiClient.get('/rooms', { params: { clinic_id: selectedClinicId.value } });
    rooms.value = data.data ?? data;
  } catch (err) {
    console.error(err);
    errorRooms.value = 'Не вдалося завантажити кабінети';
  } finally {
    loadingRooms.value = false;
  }
};

const loadWorkingHours = async () => {
  if (!selectedClinicId.value) return;
  loadingHours.value = true;
  errorHours.value = null;
  try {
    const { data } = await apiClient.get(`/clinics/${selectedClinicId.value}/working-hours`);
    workingHours.value = (data ?? [])
      .map((day) => ({
        ...day,
        start_time: normalizeTime(day.start_time),
        end_time: normalizeTime(day.end_time),
        break_start: normalizeTime(day.break_start),
        break_end: normalizeTime(day.break_end),
      }))
      .sort((a, b) => a.weekday - b.weekday);
  } catch (err) {
    console.error(err);
    errorHours.value = 'Не вдалося завантажити графік роботи';
  } finally {
    loadingHours.value = false;
  }
};

const resetRoomForm = () => {
  roomForm.value = {
    name: '',
    equipment: '',
    notes: '',
    is_active: true,
  };
};

const createRoom = async () => {
  if (!roomForm.value.name) return;
  try {
    await apiClient.post('/rooms', {
      clinic_id: selectedClinicId.value,
      ...roomForm.value,
    });
    resetRoomForm();
    await loadRooms();
  } catch (err) {
    console.error(err);
    errorRooms.value = err.response?.data?.message || 'Не вдалося створити кабінет';
  }
};

const toggleRoomActive = async (room) => {
  try {
    await apiClient.put(`/rooms/${room.id}`, {
      is_active: room.is_active,
    });
  } catch (err) {
    console.error(err);
    errorRooms.value = 'Не вдалося оновити статус кабінету';
  }
};

const deleteRoom = async (room) => {
  if (!window.confirm(`Видалити кабінет "${room.name}"?`)) return;
  try {
    await apiClient.delete(`/rooms/${room.id}`);
    await loadRooms();
  } catch (err) {
    console.error(err);
    errorRooms.value = 'Не вдалося видалити кабінет';
  }
};

const saveWorkingHours = async () => {
  if (!selectedClinicId.value) return;
  savingHours.value = true;
  errorHours.value = null;
  try {
    const payload = workingHours.value.map((day) => ({
      weekday: day.weekday,
      is_working: !!day.is_working,
      start_time: day.is_working ? day.start_time || null : null,
      end_time: day.is_working ? day.end_time || null : null,
      break_start: day.is_working ? day.break_start || null : null,
      break_end: day.is_working ? day.break_end || null : null,
    }));
    await apiClient.put(`/clinics/${selectedClinicId.value}/working-hours`, { days: payload });
    await loadWorkingHours();
  } catch (err) {
    console.error(err);
    errorHours.value = err.response?.data?.message || 'Не вдалося зберегти графік';
  } finally {
    savingHours.value = false;
  }
};

watch(selectedClinicId, async () => {
  await Promise.all([loadRooms(), loadWorkingHours()]);
});

onMounted(async () => {
  await loadClinics();
  await Promise.all([loadRooms(), loadWorkingHours()]);
});
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold">Налаштування клініки</h1>
      <p class="text-sm text-text/70">Кабінети та графік роботи клініки.</p>
    </header>

    <section class="rounded-xl border border-border bg-card/40 p-4">
      <label class="block text-xs uppercase tracking-wide text-text/70 mb-2">Клініка</label>
      <select v-model="selectedClinicId" class="rounded-lg bg-card border border-border/80 px-3 py-2 text-sm w-full md:w-72">
        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
          {{ clinic.name }}
        </option>
      </select>
    </section>

    <section class="grid lg:grid-cols-2 gap-6">
      <div class="rounded-xl border border-border bg-card/40 p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-text">Кабінети</h2>
        </div>

        <div v-if="loadingRooms" class="text-sm text-text/70">Завантаження...</div>
        <div v-else-if="errorRooms" class="text-sm text-red-400">{{ errorRooms }}</div>

        <div v-else class="space-y-3">
          <div class="space-y-2">
            <div class="grid md:grid-cols-2 gap-3">
              <input
                v-model="roomForm.name"
                type="text"
                placeholder="Назва кабінету"
                class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
              />
              <input
                v-model="roomForm.equipment"
                type="text"
                placeholder="Обладнання (опційно)"
                class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
              />
            </div>
            <textarea
              v-model="roomForm.notes"
              rows="2"
              placeholder="Нотатки"
              class="rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm w-full"
            />
            <label class="flex items-center gap-2 text-sm text-text/80">
              <input v-model="roomForm.is_active" type="checkbox" class="accent-emerald-500" />
              Активний кабінет
            </label>
            <button
              type="button"
              class="px-3 py-2 rounded-lg bg-emerald-500 text-text text-xs font-semibold hover:bg-emerald-400"
              @click="createRoom"
            >
              Додати кабінет
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-text/70 text-xs uppercase">
                <tr>
                  <th class="text-left py-2 px-3">Кабінет</th>
                  <th class="text-left py-2 px-3">Обладнання</th>
                  <th class="text-left py-2 px-3">Статус</th>
                  <th class="text-left py-2 px-3"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="room in rooms" :key="room.id" class="border-t border-border">
                  <td class="py-2 px-3 text-text/90">{{ room.name }}</td>
                  <td class="py-2 px-3 text-text/70">{{ room.equipment || '—' }}</td>
                  <td class="py-2 px-3">
                    <label class="inline-flex items-center gap-2 text-xs text-text/80">
                      <input v-model="room.is_active" type="checkbox" class="accent-emerald-500" @change="toggleRoomActive(room)" />
                      {{ room.is_active ? 'Активний' : 'Неактивний' }}
                    </label>
                  </td>
                  <td class="py-2 px-3 text-right">
                    <button class="text-xs text-red-400 hover:text-red-300" @click="deleteRoom(room)">Видалити</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="rounded-xl border border-border bg-card/40 p-4 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-text">Графік роботи</h2>
          <button
            type="button"
            class="px-3 py-2 rounded-lg bg-emerald-500 text-text text-xs font-semibold hover:bg-emerald-400 disabled:opacity-60"
            :disabled="savingHours || loadingHours"
            @click="saveWorkingHours"
          >
            {{ savingHours ? 'Збереження...' : 'Зберегти графік' }}
          </button>
        </div>

        <div v-if="loadingHours" class="text-sm text-text/70">Завантаження...</div>
        <div v-else-if="errorHours" class="text-sm text-red-400">{{ errorHours }}</div>

        <div v-else class="space-y-3">
          <div
            v-for="day in workingHours"
            :key="day.weekday"
            class="grid grid-cols-12 items-center gap-2 rounded-lg border border-border bg-bg/60 px-3 py-2 text-xs"
          >
            <div class="col-span-2 font-semibold text-text/90">
              {{ weekdays.find((w) => w.id === day.weekday)?.label }}
            </div>
            <div class="col-span-2">
              <label class="flex items-center gap-2 text-text/80">
                <input v-model="day.is_working" type="checkbox" class="accent-emerald-500" />
                Працює
              </label>
            </div>
            <div class="col-span-4 flex items-center gap-2">
              <input v-model="day.start_time" type="time" class="rounded bg-card border border-border/80 px-2 py-1" :disabled="!day.is_working" />
              <span class="text-text/60">—</span>
              <input v-model="day.end_time" type="time" class="rounded bg-card border border-border/80 px-2 py-1" :disabled="!day.is_working" />
            </div>
            <div class="col-span-4 flex items-center gap-2">
              <input v-model="day.break_start" type="time" class="rounded bg-card border border-border/80 px-2 py-1" :disabled="!day.is_working" />
              <span class="text-text/60">—</span>
              <input v-model="day.break_end" type="time" class="rounded bg-card border border-border/80 px-2 py-1" :disabled="!day.is_working" />
            </div>
          </div>
          <p class="text-[11px] text-text/60">Вкажіть час початку/закінчення та перерви для кожного робочого дня.</p>
        </div>
      </div>
    </section>
  </div>
</template>
