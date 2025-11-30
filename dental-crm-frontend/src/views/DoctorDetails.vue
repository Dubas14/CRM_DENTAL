<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';

const route = useRoute();
const router = useRouter();
const { user } = useAuth();

const doctorId = computed(() => Number(route.params.id));

const loading = ref(true);
const saving = ref(false);
const error = ref(null);
const saveError = ref(null);
const savedMessage = ref('');

const doctor = ref(null);

const form = ref({
  full_name: '',
  specialization: '',
  bio: '',
  color: '#22c55e',
  is_active: true,
});

const canEdit = computed(() => {
  if (!user.value) return false;
  if (user.value.global_role === 'super_admin') return true;
  // якщо це сам лікар
  if (doctor.value?.user_id === user.value.id) return true;
  // далі можна додати перевірку clinic_admin, коли підключимо ролі клінік на фронт
  return false;
});

const loadDoctor = async () => {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await apiClient.get(`/doctors/${doctorId.value}`);
    doctor.value = data;

    form.value = {
      full_name: data.full_name || '',
      specialization: data.specialization || '',
      bio: data.bio || '',
      color: data.color || '#22c55e',
      is_active: !!data.is_active,
    };
  } catch (e) {
    console.error(e);
    error.value = e.response?.data?.message || 'Не вдалося завантажити лікаря';
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  if (!doctor.value) return;
  form.value = {
    full_name: doctor.value.full_name || '',
    specialization: doctor.value.specialization || '',
    bio: doctor.value.bio || '',
    color: doctor.value.color || '#22c55e',
    is_active: !!doctor.value.is_active,
  };
  saveError.value = '';
  savedMessage.value = '';
};

const saveDoctor = async () => {
  if (!canEdit.value) return;

  saving.value = true;
  saveError.value = '';
  savedMessage.value = '';

  try {
    const payload = { ...form.value };
    const { data } = await apiClient.put(`/doctors/${doctorId.value}`, payload);
    doctor.value = data;

    // 🔹 Логічна поведінка:
    //   - super_admin → назад у список лікарів
    //   - інші (сам лікар у майбутньому) → залишаємо на сторінці з повідомленням
    if (user.value?.global_role === 'super_admin') {
      await router.push({ name: 'doctors' });
    } else {
      savedMessage.value = 'Зміни збережено';
    }
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

const goToSchedule = () => {
  router.push({ name: 'schedule', query: { doctor: doctorId.value } });
};

onMounted(loadDoctor);
</script>

<template>
  <div class="space-y-6">
    <button
        type="button"
        class="text-xs text-slate-400 hover:text-slate-200"
        @click="$router.back()"
    >
      ← Назад до списку лікарів
    </button>

    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ doctor?.full_name || 'Лікар' }}
        </h1>
        <p class="text-sm text-slate-400">
          Керування профілем лікаря та переходом до розкладу.
        </p>
      </div>

      <button
          type="button"
          class="px-4 py-2 rounded-lg border border-slate-700 text-sm text-slate-200 hover:bg-slate-800"
          @click="goToSchedule"
      >
        Перейти до розкладу
      </button>
      <button
          type="button"
          class="px-4 py-2 rounded-lg border border-slate-700 text-sm text-slate-200 hover:bg-slate-800"
          @click="$router.push({ name: 'doctor-weekly-schedule', params: { id: doctorId } })"
      >
        Налаштувати тижневий розклад
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-400">
      Завантаження даних лікаря...
    </div>

    <div v-else-if="error" class="text-sm text-red-400">
      ❌ {{ error }}
    </div>

    <div v-else class="grid gap-6 md:grid-cols-[2fr,1fr]">
      <!-- Профіль лікаря -->
      <section
          class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4"
      >
        <h2 class="text-sm font-semibold text-slate-200 mb-2">
          Анкетні дані
        </h2>

        <div v-if="saveError" class="text-sm text-red-400">
          ❌ {{ saveError }}
        </div>
        <div v-if="savedMessage" class="text-sm text-emerald-400">
          ✅ {{ savedMessage }}
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs uppercase text-slate-400 mb-1">
              ПІБ
            </label>
            <input
                v-model="form.full_name"
                :disabled="!canEdit"
                type="text"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 disabled:opacity-70"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-slate-400 mb-1">
              Спеціалізація
            </label>
            <input
                v-model="form.specialization"
                :disabled="!canEdit"
                type="text"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 disabled:opacity-70"
            />
          </div>

          <div>
            <label class="block text-xs uppercase text-slate-400 mb-1">
              Коротке біо
            </label>
            <textarea
                v-model="form.bio"
                :disabled="!canEdit"
                rows="3"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 disabled:opacity-70"
            />
          </div>

          <div class="flex flex-wrap items-center gap-4">
            <div>
              <label class="block text-xs uppercase text-slate-400 mb-1">
                Колір картки
              </label>
              <input
                  v-model="form.color"
                  :disabled="!canEdit"
                  type="color"
                  class="h-10 w-20 rounded-lg bg-slate-950 border border-slate-700"
              />
            </div>
            <div class="flex items-center gap-2 mt-4">
              <input
                  id="active"
                  v-model="form.is_active"
                  :disabled="!canEdit"
                  type="checkbox"
                  class="h-4 w-4 rounded border-slate-600 bg-slate-900"
              />
              <label for="active" class="text-sm text-slate-200">
                Активний лікар
              </label>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-4" v-if="canEdit">
          <button
              type="button"
              class="px-3 py-2 rounded-lg border border-slate-700 text-sm text-slate-300 hover:bg-slate-800"
              @click="resetForm"
          >
            Скасувати
          </button>
          <button
              type="button"
              :disabled="saving"
              class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400 disabled:opacity-60"
              @click="saveDoctor"
          >
            {{ saving ? 'Збереження...' : 'Зберегти' }}
          </button>
        </div>
      </section>

      <!-- Інфо про акаунт -->
      <section
          class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3"
      >
        <h2 class="text-sm font-semibold text-slate-200">
          Акаунт користувача
        </h2>
        <p class="text-xs text-slate-400">
          Цей лікар прив’язаний до користувача системи.
        </p>
        <div class="space-y-2 text-sm">
          <div>
            <span class="text-slate-400">Email (логін): </span>
            <span class="text-slate-100">
              {{ doctor?.user?.email || '—' }}
            </span>
          </div>
          <div>
            <span class="text-slate-400">Клініка: </span>
            <span class="text-slate-100">
              {{ doctor?.clinic?.name || '—' }}
            </span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
