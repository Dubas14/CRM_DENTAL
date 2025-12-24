<script setup>
import { ref, onMounted, watch } from 'vue';
import equipmentApi from '../services/equipmentApi';
import clinicApi from '../services/clinicApi';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

const clinics = ref([]);
const selectedClinicId = ref('');
const equipments = ref([]);
const loading = ref(false);
const error = ref(null);

const showForm = ref(false);
const creating = ref(false);
const createError = ref(null);
const editError = ref(null);
const editingEquipmentId = ref(null);
const editForm = ref({
  name: '',
  description: '',
  is_active: true,
});
const savingEdit = ref(false);

const form = ref({
  clinic_id: '',
  name: '',
  description: '',
  is_active: true,
});

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

const fetchEquipments = async () => {
  if (!selectedClinicId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const { data } = await equipmentApi.list({ clinic_id: selectedClinicId.value });
    equipments.value = data.data ?? data;
  } catch (err) {
    console.error(err);
    error.value = 'Не вдалося завантажити обладнання';
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  form.value = {
    clinic_id: selectedClinicId.value || clinics.value[0]?.id || '',
    name: '',
    description: '',
    is_active: true,
  };
};

const toggleForm = () => {
  showForm.value = !showForm.value;
  if (showForm.value) {
    resetForm();
  }
};

const createEquipment = async () => {
  creating.value = true;
  createError.value = null;
  try {
    await equipmentApi.create({
      ...form.value,
      clinic_id: form.value.clinic_id || selectedClinicId.value,
    });
    showForm.value = false;
    resetForm();
    await fetchEquipments();
  } catch (err) {
    console.error(err);
    createError.value = err.response?.data?.message || 'Помилка створення обладнання';
  } finally {
    creating.value = false;
  }
};

const startEdit = (equipment) => {
  editingEquipmentId.value = equipment.id;
  editError.value = null;
  editForm.value = {
    name: equipment.name || '',
    description: equipment.description || '',
    is_active: !!equipment.is_active,
  };
};

const cancelEdit = () => {
  editingEquipmentId.value = null;
  editError.value = null;
};

const updateEquipment = async (equipment) => {
  savingEdit.value = true;
  editError.value = null;
  try {
    await equipmentApi.update(equipment.id, {
      name: editForm.value.name,
      description: editForm.value.description || null,
      is_active: editForm.value.is_active,
    });
    await fetchEquipments();
    editingEquipmentId.value = null;
  } catch (err) {
    console.error(err);
    editError.value = err.response?.data?.message || 'Не вдалося оновити обладнання';
  } finally {
    savingEdit.value = false;
  }
};

const deleteEquipment = async (equipment) => {
  if (!window.confirm(`Видалити обладнання "${equipment.name}"?`)) return;
  editError.value = null;
  try {
    await equipmentApi.delete(equipment.id);
    await fetchEquipments();
  } catch (err) {
    console.error(err);
    editError.value = err.response?.data?.message || 'Не вдалося видалити обладнання';
  }
};

watch(selectedClinicId, async () => {
  await fetchEquipments();
});

onMounted(async () => {
  await loadClinics();
  await fetchEquipments();
});
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Обладнання</h1>
        <p class="text-sm text-slate-400">Створення та перегляд обладнання для клініки.</p>
      </div>
      <button
        class="px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Нове обладнання' }}
      </button>
    </header>

    <div class="flex flex-wrap items-center gap-3">
      <label class="text-xs uppercase text-slate-400">Клініка</label>
      <select
        v-model="selectedClinicId"
        class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
      >
        <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
          {{ clinic.name }}
        </option>
      </select>
    </div>

    <section
      v-if="showForm"
      class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-4"
    >
      <h2 class="text-sm font-semibold text-slate-200">Додати нове обладнання</h2>

      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Клініка</label>
          <select
            v-model="form.clinic_id"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          >
            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
              {{ clinic.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Назва</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs uppercase text-slate-400 mb-1">Опис</label>
        <textarea
          v-model="form.description"
          rows="2"
          class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
        />
      </div>

      <label class="flex items-center gap-2 text-sm text-slate-300">
        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-700 bg-slate-950" />
        Активне обладнання
      </label>

      <div class="flex items-center justify-between gap-3">
        <span v-if="createError" class="text-sm text-red-400">❌ {{ createError }}</span>
        <button
          class="ml-auto px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
          :disabled="creating"
          @click="createEquipment"
        >
          {{ creating ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </section>

    <section class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
      <div v-if="loading" class="text-sm text-slate-400">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="editError" class="text-sm text-red-400">{{ editError }}</div>
      <div v-else-if="!equipments.length" class="text-sm text-slate-400">Немає обладнання.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-slate-400 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Назва</th>
              <th class="text-left py-2 px-3">Опис</th>
              <th class="text-left py-2 px-3">Статус</th>
              <th class="text-right py-2 px-3">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="equipment in equipments" :key="equipment.id" class="border-t border-slate-800">
              <td class="py-2 px-3">
                <input
                  v-if="editingEquipmentId === equipment.id"
                  v-model="editForm.name"
                  type="text"
                  class="w-full rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-sm text-slate-100"
                />
                <span v-else class="text-slate-200">{{ equipment.name }}</span>
              </td>
              <td class="py-2 px-3">
                <input
                  v-if="editingEquipmentId === equipment.id"
                  v-model="editForm.description"
                  type="text"
                  class="w-full rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-sm text-slate-100"
                />
                <span v-else class="text-slate-400">{{ equipment.description || '—' }}</span>
              </td>
              <td class="py-2 px-3">
                <label v-if="editingEquipmentId === equipment.id" class="inline-flex items-center gap-2 text-xs text-slate-300">
                  <input v-model="editForm.is_active" type="checkbox" class="accent-emerald-500" />
                  {{ editForm.is_active ? 'Активне' : 'Неактивне' }}
                </label>
                <span
                  v-else
                  class="px-2 py-1 rounded-full text-xs"
                  :class="equipment.is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-400'"
                >
                  {{ equipment.is_active ? 'Активне' : 'Неактивне' }}
                </span>
              </td>
              <td class="py-2 px-3 text-right text-xs">
                <div v-if="editingEquipmentId === equipment.id" class="flex justify-end gap-3">
                  <button
                    class="text-emerald-300 hover:text-emerald-200 disabled:opacity-60"
                    :disabled="savingEdit"
                    @click="updateEquipment(equipment)"
                  >
                    {{ savingEdit ? 'Збереження...' : 'Зберегти' }}
                  </button>
                  <button class="text-slate-400 hover:text-slate-200" @click="cancelEdit">Скасувати</button>
                </div>
                <div v-else class="flex justify-end gap-3">
                  <button class="text-emerald-300 hover:text-emerald-200" @click="startEdit(equipment)">Редагувати</button>
                  <button class="text-red-400 hover:text-red-300" @click="deleteEquipment(equipment)">Видалити</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
