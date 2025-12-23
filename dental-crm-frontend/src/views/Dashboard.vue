<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useAuth } from '../composables/useAuth';
import { usePermissions } from '../composables/usePermissions';
import apiClient from '../services/apiClient';
import calendarApi from '../services/calendarApi';
import { Users, Calendar, Clock, Activity } from 'lucide-vue-next';
import ActivityChart from '../components/ActivityChart.vue';

const { user } = useAuth();
const { role, isDoctor } = usePermissions();

const stats = ref({
  patientsCount: 0,
  appointmentsToday: 0,
  nextAppointment: null
});

const loading = ref(true);
const weeklyActivity = ref([]);
const upcomingAppointments = ref([]);

const daysShort = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

const greetingName = computed(() => {
  if (!user.value) return 'гість';

  if (['super_admin', 'clinic_admin'].includes(role.value)) {
    return 'Адміністратор';
  }

  if (user.value.doctor) {
    const doc = user.value.doctor;
    return doc.full_name || `${doc.first_name || ''} ${doc.last_name || ''}`.trim();
  }

  return `${user.value.first_name || ''} ${user.value.last_name || ''}`.trim() || 'користувач';
});

const greetingSubtitle = computed(() => {
  if (!user.value) return 'Ласкаво просимо!';

  if (role.value === 'super_admin') return 'Суперадміністратор';
  if (role.value === 'clinic_admin') return 'Адміністратор клініки';
  if (role.value === 'doctor') return 'Лікар';
  if (role.value === 'registrar') return 'Реєстратор';

  return 'Користувач';
});

const formatDateYMD = (date) => date.toISOString().slice(0, 10);

const parseAppointmentDate = (appt) => {
  if (appt.start_at) return new Date(appt.start_at);
  if (appt.date && appt.time) return new Date(`${appt.date}T${appt.time}`);
  if (appt.date) return new Date(`${appt.date}T00:00:00`);
  return null;
};

const normalizeCollection = (payload) => {
  const items = Array.isArray(payload?.data)
    ? payload.data
    : Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data?.data)
        ? payload.data.data
        : [];

  const total = payload?.meta?.total
    ?? payload?.total
    ?? payload?.data?.total
    ?? items.length;

  return { items, total };
};

const formatTime = (date) => date?.toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' }) || '—';
const formatDayMonth = (date) => date?.toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit' }) || '';

const loadStats = async () => {
  loading.value = true;
  const today = new Date();
  const startRange = new Date(today);
  startRange.setDate(today.getDate() - 1);
  const rangeEnd = new Date(today);
  rangeEnd.setDate(today.getDate() + 7);

  const appointmentParams = {
    from_date: formatDateYMD(startRange),
    to_date: formatDateYMD(rangeEnd)
  };

  const doctorId = user.value?.doctor?.id;
  if (isDoctor.value && doctorId) {
    appointmentParams.doctor_id = doctorId;
  }

  try {
    const [patientsResponse, appointmentsResponse] = await Promise.allSettled([
      apiClient.get('/patients'),
      calendarApi.getAppointments(appointmentParams)
    ]);

    if (patientsResponse.status === 'fulfilled') {
      const normalizedPatients = normalizeCollection(patientsResponse.value.data);
      stats.value.patientsCount = normalizedPatients.total || normalizedPatients.items.length;
    }

    if (appointmentsResponse.status === 'fulfilled') {
      const normalizedAppointments = normalizeCollection(appointmentsResponse.value.data);

      const mappedAppointments = normalizedAppointments.items
        .map((appt) => {
          const startDate = parseAppointmentDate(appt);
          return {
            ...appt,
            startDate,
            patientLabel: appt.patient?.full_name || appt.patient_name || appt.patient?.name || '—',
            procedureName: appt.procedure?.name || '',
            displayTime: formatTime(startDate) || (appt.time ? appt.time.slice(0, 5) : '—'),
            displayDate: formatDayMonth(startDate) || appt.date || '',
          };
        })
        .filter((appt) => appt.startDate);

      const todayStr = formatDateYMD(today);
      const now = Date.now();
      const todayAppointments = mappedAppointments.filter((appt) => formatDateYMD(appt.startDate) === todayStr);
      stats.value.appointmentsToday = todayAppointments.length;

      const upcoming = mappedAppointments
        .filter((appt) => appt.startDate.getTime() >= now && appt.status !== 'cancelled')
        .sort((a, b) => a.startDate - b.startDate);

      stats.value.nextAppointment = upcoming[0] || null;
      upcomingAppointments.value = upcoming.slice(0, 5);

      const rangeMap = Array.from({ length: 7 }).map((_, index) => {
        const d = new Date(today);
        d.setDate(today.getDate() + index);
        return {
          key: formatDateYMD(d),
          day: daysShort[d.getDay()],
          value: 0
        };
      });

      mappedAppointments.forEach((appt) => {
        const dayKey = formatDateYMD(appt.startDate);
        const entry = rangeMap.find((item) => item.key === dayKey);
        if (entry) entry.value += 1;
      });

      weeklyActivity.value = rangeMap.map(({ day, value }) => ({ day, value }));
    } else {
      weeklyActivity.value = [];
      upcomingAppointments.value = [];
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  if (user.value) {
    loadStats();
  }
});

watch(() => user.value, (val) => {
  if (val) loadStats();
});
</script>

<template>
  <div class="space-y-6 animate-fade-in">
    <!-- Привітання -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 p-8 text-white shadow-lg">
      <div class="relative z-10">
        <div class="flex items-center gap-3 mb-1">
          <h1 class="text-3xl font-bold">Вітаємо, {{ greetingName }}! 👋</h1>
          <span class="px-3 py-1 rounded-full bg-white/15 text-sm font-semibold">{{ greetingSubtitle }}</span>
        </div>
        <p class="text-emerald-100 text-lg">Гарного робочого дня. Сьогодні у вас {{ stats.appointmentsToday }} пацієнтів.</p>
      </div>
      <!-- Декоративні кола -->
      <div class="absolute top-0 right-0 -mt-10 -mr-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
      <div class="absolute bottom-0 left-0 -mb-10 -ml-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
    </div>

    <!-- Картки статистики -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- Пацієнти -->
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-md hover:shadow-xl hover:border-emerald-500/30 transition-all duration-300 group">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-slate-400 text-sm font-medium uppercase">Всього пацієнтів</p>
            <h3 class="text-3xl font-bold text-white mt-2">{{ stats.patientsCount }}</h3>
          </div>
          <div class="p-3 bg-slate-800 rounded-lg text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
            <Users size="24" />
          </div>
        </div>
      </div>

      <!-- Записи сьогодні -->
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-md hover:shadow-xl hover:border-blue-500/30 transition-all duration-300 group">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-slate-400 text-sm font-medium uppercase">Записи сьогодні</p>
            <h3 class="text-3xl font-bold text-white mt-2">{{ stats.appointmentsToday }}</h3>
          </div>
          <div class="p-3 bg-slate-800 rounded-lg text-blue-400 group-hover:bg-blue-500 group-hover:text-white transition-colors">
            <Calendar size="24" />
          </div>
        </div>
      </div>

      <!-- Активність -->
      <div class="bg-slate-900 border border-slate-800 p-6 rounded-xl shadow-md hover:shadow-xl hover:border-purple-500/30 transition-all duration-300 group">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-slate-400 text-sm font-medium uppercase">Найближчий візит</p>
            <h3 class="text-xl font-bold text-white mt-2 truncate">
              {{ stats.nextAppointment ? stats.nextAppointment.displayTime : '—' }}
            </h3>
            <p class="text-xs text-slate-500 mt-1" v-if="stats.nextAppointment">
              {{ stats.nextAppointment.patientLabel || 'Без імені' }}
              <span v-if="stats.nextAppointment.displayDate" class="text-slate-600">· {{ stats.nextAppointment.displayDate }}</span>
            </p>
          </div>
          <div class="p-3 bg-slate-800 rounded-lg text-purple-400 group-hover:bg-purple-500 group-hover:text-white transition-colors">
            <Clock size="24" />
          </div>
        </div>
      </div>
    </div>

    <!-- Найближчі візити -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-md">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-bold text-white flex items-center gap-2">
            <Clock size="18" class="text-emerald-400" />
            Найближчі візити
          </h3>
          <p class="text-slate-500 text-sm">Перші 5 записів з найближчим часом</p>
        </div>
        <router-link :to="{ name: 'schedule' }" class="text-sm text-emerald-400 hover:text-emerald-300">Перейти до розкладу →</router-link>
      </div>

      <div v-if="loading" class="text-slate-500 text-sm">Завантаження...</div>
      <div v-else-if="!upcomingAppointments.length" class="text-slate-500 text-sm">Найближчих записів немає.</div>
      <ul v-else class="space-y-3">
        <li v-for="appt in upcomingAppointments" :key="appt.id" class="flex items-center justify-between bg-slate-950 border border-slate-800 rounded-lg px-4 py-3 hover:border-emerald-500/40 transition-colors">
          <div>
            <p class="text-white font-semibold">
              {{ appt.patientLabel }}
              <span v-if="appt.procedureName" class="text-slate-400 text-xs font-normal">· {{ appt.procedureName }}</span>
            </p>
            <p class="text-slate-500 text-xs mt-1">{{ appt.displayDate }} · {{ appt.displayTime }}</p>
          </div>
          <span class="text-emerald-400 font-mono text-sm">{{ appt.displayTime }}</span>
        </li>
      </ul>
    </div>

    <!-- Секція швидких дій -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
          <Activity size="20" class="text-emerald-400"/>
          Швидкі дії
        </h3>
        <div class="grid grid-cols-2 gap-4">
          <router-link :to="{name: 'schedule'}" class="flex flex-col items-center justify-center p-4 bg-slate-950 border border-slate-800 rounded-lg hover:bg-slate-800 transition-colors cursor-pointer group">
            <Calendar class="text-emerald-500 mb-2 group-hover:scale-110 transition-transform" size="28"/>
            <span class="text-slate-300 text-sm">Мій розклад</span>
          </router-link>
          <router-link :to="{name: 'patients'}" class="flex flex-col items-center justify-center p-4 bg-slate-950 border border-slate-800 rounded-lg hover:bg-slate-800 transition-colors cursor-pointer group">
            <Users class="text-blue-500 mb-2 group-hover:scale-110 transition-transform" size="28"/>
            <span class="text-slate-300 text-sm">База пацієнтів</span>
          </router-link>
        </div>
      </div>

      <ActivityChart :data="weeklyActivity" title="Активність за тиждень" />
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>