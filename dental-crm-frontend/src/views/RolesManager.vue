<script setup>
import { ref, onMounted } from 'vue';
import { debounce } from 'lodash-es';
import roleApi from '../services/roleApi';

const roles = ref([]);
const users = ref([]);
const editableRoles = ref({});
const loading = ref(false);
const error = ref(null);
const saving = ref({});
const searchQuery = ref('');
const currentPage = ref(1);
const perPage = 15;
const totalPages = ref(1);
const totalItems = ref(0);

const fetchRoles = async () => {
  const { data } = await roleApi.listRoles();
  roles.value = data.roles ?? [];
};

const fetchUsers = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await roleApi.listUsers({
      page: currentPage.value,
      per_page: perPage,
      search: searchQuery.value || undefined,
    });
    users.value = data.data ?? data;
    editableRoles.value = users.value.reduce((acc, user) => {
      acc[user.id] = (user.roles || []).map((role) => role.name);
      return acc;
    }, {});
    const meta = data.meta ?? data ?? {};
    totalPages.value = meta.last_page ?? 1;
    totalItems.value = meta.total ?? users.value.length;
    currentPage.value = meta.current_page ?? currentPage.value;
  } catch (err) {
    console.error(err);
    error.value = 'Не вдалося завантажити користувачів';
  } finally {
    loading.value = false;
  }
};

const displayName = (user) => {
  if (user.first_name || user.last_name) {
    return `${user.first_name || ''} ${user.last_name || ''}`.trim();
  }
  return user.name || user.email;
};

const updateUserRoles = async (userId) => {
  saving.value[userId] = true;
  try {
    const { data } = await roleApi.updateUserRoles(userId, editableRoles.value[userId]);
    editableRoles.value[userId] = data.roles ?? editableRoles.value[userId];
  } catch (err) {
    console.error(err);
    error.value = err.response?.data?.message || 'Не вдалося оновити ролі';
  } finally {
    saving.value[userId] = false;
  }
};

onMounted(async () => {
  await fetchRoles();
  await fetchUsers();
});

const debouncedSearch = debounce(() => {
  currentPage.value = 1;
  fetchUsers();
}, 300);

const onSearchInput = () => {
  debouncedSearch();
};

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value || page === currentPage.value) return;
  currentPage.value = page;
  fetchUsers();
};
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold">Ролі користувачів</h1>
      <p class="text-sm text-text/70">
        Призначайте ролі відповідно до ієрархії доступів.
      </p>
    </header>

    <section class="rounded-xl border border-border bg-card/40 p-4 space-y-4">
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px]">
          <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">Пошук користувача</label>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Імʼя, прізвище або email"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text/90"
            @input="onSearchInput"
          />
        </div>
        <div class="text-xs text-text/60">
          Показано {{ users.length }} із {{ totalItems }} користувачів
        </div>
      </div>
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="!users.length" class="text-sm text-text/70">Немає користувачів.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-text/70 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Користувач</th>
              <th class="text-left py-2 px-3">Email</th>
              <th class="text-left py-2 px-3">Ролі</th>
              <th class="text-left py-2 px-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-t border-border">
              <td class="py-2 px-3 text-text/90">{{ displayName(user) }}</td>
              <td class="py-2 px-3 text-text/70">{{ user.email }}</td>
              <td class="py-2 px-3">
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="role in roles"
                    :key="role"
                    class="flex items-center gap-2 rounded-lg border border-border/80 px-2 py-1 text-xs text-text/80"
                  >
                    <input
                      v-model="editableRoles[user.id]"
                      type="checkbox"
                      :value="role"
                      class="rounded border-border/80 bg-bg"
                    />
                    {{ role }}
                  </label>
                </div>
              </td>
              <td class="py-2 px-3 text-right">
                <button
                  class="px-3 py-2 rounded-lg bg-emerald-500 text-text text-xs font-semibold hover:bg-emerald-400 disabled:opacity-60"
                  :disabled="saving[user.id]"
                  @click="updateUserRoles(user.id)"
                >
                  {{ saving[user.id] ? 'Збереження...' : 'Зберегти' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="totalPages > 1" class="flex items-center justify-between gap-3">
        <button
          class="px-3 py-2 rounded-lg border border-border/80 text-xs text-text/90 hover:bg-card/80 disabled:opacity-50"
          :disabled="currentPage === 1"
          @click="goToPage(currentPage - 1)"
        >
          Попередня
        </button>
        <div class="text-xs text-text/60">
          Сторінка {{ currentPage }} з {{ totalPages }}
        </div>
        <button
          class="px-3 py-2 rounded-lg border border-border/80 text-xs text-text/90 hover:bg-card/80 disabled:opacity-50"
          :disabled="currentPage === totalPages"
          @click="goToPage(currentPage + 1)"
        >
          Наступна
        </button>
      </div>
    </section>
  </div>
</template>
