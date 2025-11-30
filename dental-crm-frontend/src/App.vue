<script setup>
import { RouterView, RouterLink, useRouter, useRoute } from 'vue-router';
import { onMounted, computed } from 'vue';
import { useAuth } from './composables/useAuth';

const router = useRouter();
const route = useRoute();

const { user, isLoggedIn, fetchUser, logout } = useAuth();

onMounted(() => {
  fetchUser().catch(() => {});
});

const handleLogout = async () => {
  await logout();
  router.push({ name: 'login' });
};

const showHeader = computed(() => route.name !== 'login' && isLoggedIn.value);

// 🔹 тільки супер-адмін
const isSuperAdmin = computed(() => user.value?.global_role === 'super_admin');
</script>

<<template>
  <div class="min-h-screen bg-slate-900 text-slate-100">
    <header
        v-if="showHeader"
        class="sticky top-0 z-10 bg-slate-900/90 backdrop-blur border-b border-slate-800"
    >
      <div
          class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-6"
      >
        <!-- Лого -->
        <div class="flex items-center gap-2">
          <span class="text-xl font-semibold">Dental CRM</span>
          <span
              class="text-xs text-emerald-300 border border-emerald-500/40 rounded-full px-2 py-0.5"
          >
            dev
          </span>
        </div>

        <!-- Навігація -->
        <nav class="text-sm flex gap-4">
          <!-- Клініки бачить лише супер-адмін -->
          <RouterLink
              v-if="isSuperAdmin"
              to="/clinics"
              class="text-slate-300 hover:text-white"
              active-class="text-white font-semibold"
          >
            Клініки
          </RouterLink>

          <RouterLink
              to="/doctors"
              class="text-slate-300 hover:text-white"
              active-class="text-white font-semibold"
          >
            Лікарі
          </RouterLink>

          <RouterLink
              to="/schedule"
              class="text-slate-300 hover:text-white"
              active-class="text-white font-semibold"
          >
            Розклад
          </RouterLink>

          <RouterLink
              to="/patients"
              class="text-slate-300 hover:text-white"
              active-class="text-white font-semibold"
          >
            Пацієнти
          </RouterLink>
        </nav>


        <!-- Юзер -->
        <div class="flex items-center gap-3">
          <span class="text-sm text-slate-300">
            {{ user?.name }}
            <span class="text-xs text-emerald-400">
              ({{ user?.global_role }})
            </span>
          </span>
          <button
              type="button"
              class="text-sm text-slate-300 hover:text-red-400"
              @click="handleLogout"
          >
            Вийти
          </button>
        </div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6">
      <RouterView />
    </main>
  </div>
</template>

