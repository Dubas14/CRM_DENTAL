<script setup>
import { ref, onMounted } from 'vue';
import roleApi from '../services/roleApi';

const roles = ref([]);
const users = ref([]);
const editableRoles = ref({});
const loading = ref(false);
const error = ref(null);
const saving = ref({});

const fetchRoles = async () => {
  const { data } = await roleApi.listRoles();
  roles.value = data.roles ?? [];
};

const fetchUsers = async () => {
  loading.value = true;
  error.value = null;
  try {
    const { data } = await roleApi.listUsers();
    users.value = data.data ?? data;
    editableRoles.value = users.value.reduce((acc, user) => {
      acc[user.id] = (user.roles || []).map((role) => role.name);
      return acc;
    }, {});
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
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold">Ролі користувачів</h1>
      <p class="text-sm text-slate-400">
        Призначайте ролі відповідно до ієрархії доступів.
      </p>
    </header>

    <section class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
      <div v-if="loading" class="text-sm text-slate-400">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="!users.length" class="text-sm text-slate-400">Немає користувачів.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-slate-400 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Користувач</th>
              <th class="text-left py-2 px-3">Email</th>
              <th class="text-left py-2 px-3">Ролі</th>
              <th class="text-left py-2 px-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-t border-slate-800">
              <td class="py-2 px-3 text-slate-200">{{ displayName(user) }}</td>
              <td class="py-2 px-3 text-slate-400">{{ user.email }}</td>
              <td class="py-2 px-3">
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="role in roles"
                    :key="role"
                    class="flex items-center gap-2 rounded-lg border border-slate-700 px-2 py-1 text-xs text-slate-300"
                  >
                    <input
                      v-model="editableRoles[user.id]"
                      type="checkbox"
                      :value="role"
                      class="rounded border-slate-700 bg-slate-950"
                    />
                    {{ role }}
                  </label>
                </div>
              </td>
              <td class="py-2 px-3 text-right">
                <button
                  class="px-3 py-2 rounded-lg bg-emerald-500 text-slate-900 text-xs font-semibold hover:bg-emerald-400 disabled:opacity-60"
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
    </section>
  </div>
</template>
