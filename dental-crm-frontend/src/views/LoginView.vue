<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { login } = useAuth()

const email = ref('')
const password = ref('')
const rememberMe = ref(true)

const showPassword = ref(false)
const loading = ref(false)
const error = ref(null)

const passwordType = computed(() => (showPassword.value ? 'text' : 'password'))

// ✅ Варіант 2: показувати RouterLink лише якщо такий роут існує
const hasForgotRoute = computed(() => {
  return router.getRoutes().some((r) => r.name === 'forgot-password')
})

const handleSubmit = async () => {
  loading.value = true
  error.value = null

  try {
    // Прапорець "запам'ятати мене" (бекенд логіку можна підключити пізніше)
    localStorage.setItem('remember_me', rememberMe.value ? '1' : '0')

    await login(email.value.trim(), password.value)

    await router.push({ name: 'dashboard' })
  } catch (e) {
    console.error(e)
    error.value = e.response?.data?.message || 'Невірний логін або пароль'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-bg relative overflow-hidden px-4">
    <!-- фон -->
    <div class="absolute inset-0 opacity-40">
      <div
        class="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-emerald-500/30 blur-3xl"
      ></div>
      <div
        class="absolute -bottom-40 -right-40 h-96 w-96 rounded-full bg-cyan-500/20 blur-3xl"
      ></div>
    </div>

    <div class="relative w-full max-w-md">
      <div
        class="rounded-2xl bg-card/70 shadow-sm shadow-black/10 dark:shadow-black/40 p-6 shadow-2xl backdrop-blur"
      >
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-text">Dental CRM</h1>
          <p class="text-sm text-text/70 mt-1">Вхід до системи</p>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <!-- Email -->
          <div>
            <label class="block text-xs font-medium text-text/80 mb-1"> Email </label>

            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text/60">
                <!-- mail icon -->
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M4 6h16"></path>
                  <path d="m4 6 8 7 8-7"></path>
                  <path d="M4 18h16"></path>
                </svg>
              </span>

              <input
                v-model="email"
                type="email"
                required
                autocomplete="username"
                inputmode="email"
                placeholder="name@clinic.com"
                class="w-full rounded-xl bg-bg/60 border border-border/80/70 pl-10 pr-3 py-2.5 text-sm text-text placeholder:text-text/60 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-medium text-text/80"> Пароль </label>

              <!-- ✅ Варіант 2: RouterLink якщо роут існує, інакше - "заглушка" -->
              <RouterLink
                v-if="hasForgotRoute"
                :to="{ name: 'forgot-password' }"
                class="text-xs text-emerald-400 hover:text-emerald-300"
              >
                Забули пароль?
              </RouterLink>

              <a
                v-else
                href="#"
                class="text-xs text-emerald-400 hover:text-emerald-300"
                @click.prevent="error = 'Функція “Забули пароль?” ще в розробці'"
              >
                Забули пароль?
              </a>
            </div>

            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-text/60">
                <!-- lock icon -->
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M17 11V7a5 5 0 0 0-10 0v4"></path>
                  <rect x="5" y="11" width="14" height="10" rx="2"></rect>
                </svg>
              </span>

              <input
                v-model="password"
                :type="passwordType"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full rounded-xl bg-bg/60 border border-border/80/70 pl-10 pr-11 py-2.5 text-sm text-text placeholder:text-text/60 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500/50"
              />

              <button
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-text/70 hover:text-text/90 hover:bg-card/60"
                @click="showPassword = !showPassword"
                :aria-label="showPassword ? 'Сховати пароль' : 'Показати пароль'"
              >
                <!-- eye / eye-off -->
                <svg
                  v-if="!showPassword"
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M3 3l18 18"></path>
                  <path d="M10.58 10.58A2 2 0 0 0 12 14a2 2 0 0 0 1.42-.58"></path>
                  <path
                    d="M9.88 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a18.16 18.16 0 0 1-3.22 4.4"
                  ></path>
                  <path d="M6.1 6.1C3.6 8 2 12 2 12s3 7 10 7a10.44 10.44 0 0 0 4.2-.86"></path>
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember me -->
          <div class="flex items-center justify-between">
            <label class="inline-flex items-center gap-2 select-none">
              <input
                v-model="rememberMe"
                type="checkbox"
                class="h-4 w-4 rounded border-border/70 bg-bg/60 text-emerald-500 focus:ring-emerald-500/50"
              />
              <span class="text-sm text-text/80">Запам’ятати мене</span>
            </label>
          </div>

          <!-- Error -->
          <div
            v-if="error"
            class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200"
          >
            ❌ {{ error }}
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="loading || !email || !password"
            class="w-full mt-1 px-4 py-2.5 rounded-xl bg-emerald-500 text-text font-semibold text-sm hover:bg-emerald-400 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <svg
              v-if="loading"
              xmlns="http://www.w3.org/2000/svg"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
            >
              <circle
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="3"
                opacity="0.25"
              ></circle>
              <path
                d="M22 12a10 10 0 0 0-10-10"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
              ></path>
            </svg>
            {{ loading ? 'Вхід...' : 'Увійти' }}
          </button>
        </form>

        <div class="mt-6 text-center text-xs text-text/60">
          © {{ new Date().getFullYear() }} Dental CRM
        </div>
      </div>
    </div>
  </div>
</template>
