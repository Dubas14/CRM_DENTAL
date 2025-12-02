<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';

const router = useRouter();
const { login } = useAuth();

const email = ref('admin@example.com');
const password = ref('password');
const loading = ref(false);
const error = ref(null);

const handleSubmit = async () => {
  loading.value = true;
  error.value = null;
  try {
    const loggedUser = await login(email.value, password.value);

    const target =
        (loggedUser.is_admin === true || loggedUser.is_admin === 1)
            ? '/clinics'
            : '/schedule';

    await router.push(target);
  } catch (e) {
    console.error(e);
    error.value =
        e.response?.data?.message || 'Невірний логін або пароль';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-950">
    <div class="w-full max-w-md rounded-2xl bg-slate-900/80 border border-slate-700 p-6 shadow-xl">
      <h1 class="text-2xl font-bold text-white mb-2">Dental CRM</h1>
      <p class="text-sm text-slate-400 mb-4">
        Вхід до системи
      </p>

      <form class="space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">
            Email
          </label>
          <input
              v-model="email"
              type="email"
              required
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">
            Пароль
          </label>
          <input
              v-model="password"
              type="password"
              required
              class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div v-if="error" class="text-sm text-red-400">
          ❌ {{ error }}
        </div>

        <button
            type="submit"
            :disabled="loading"
            class="w-full mt-2 px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 font-semibold text-sm hover:bg-emerald-400 disabled:opacity-60"
        >
          {{ loading ? 'Вхід...' : 'Увійти' }}
        </button>
      </form>
    </div>
  </div>
</template>
