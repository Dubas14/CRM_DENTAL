<script setup>
import { ref, onMounted, watch } from 'vue';
import procedureApi from '../services/procedureApi';
import equipmentApi from '../services/equipmentApi';
import clinicApi from '../services/clinicApi';
import { useAuth } from '../composables/useAuth';

const { user } = useAuth();

const clinics = ref([]);
const selectedClinicId = ref('');
const procedures = ref([]);
const equipments = ref([]);
const loading = ref(false);
const error = ref(null);

const showForm = ref(false);
const creating = ref(false);
const createError = ref(null);
const editError = ref(null);
const editingProcedureId = ref(null);
const editForm = ref({
  name: '',
  category: '',
  duration_minutes: 30,
  requires_room: false,
  requires_assistant: false,
  equipment_id: '',
});
const savingEdit = ref(false);

const form = ref({
  clinic_id: '',
  name: '',
  category: '',
  duration_minutes: 30,
  requires_room: false,
  requires_assistant: false,
  equipment_id: '',
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
  const { data } = await equipmentApi.list({ clinic_id: selectedClinicId.value });
  equipments.value = data.data ?? data;
};

const fetchProcedures = async () => {
  if (!selectedClinicId.value) return;
  loading.value = true;
  error.value = null;
  try {
    const { data } = await procedureApi.list({ clinic_id: selectedClinicId.value });
    procedures.value = data.data ?? data;
  } catch (err) {
    console.error(err);
    error.value = 'Не вдалося завантажити процедури';
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  form.value = {
    clinic_id: selectedClinicId.value || clinics.value[0]?.id || '',
    name: '',
    category: '',
    duration_minutes: 30,
    requires_room: false,
    requires_assistant: false,
    equipment_id: '',
  };
};

const toggleForm = () => {
  showForm.value = !showForm.value;
  if (showForm.value) {
    resetForm();
  }
};

const createProcedure = async () => {
  creating.value = true;
  createError.value = null;
  try {
    await procedureApi.create({
      ...form.value,
      clinic_id: form.value.clinic_id || selectedClinicId.value,
      equipment_id: form.value.equipment_id || null,
    });
    showForm.value = false;
    resetForm();
    await fetchProcedures();
  } catch (err) {
    console.error(err);
    createError.value = err.response?.data?.message || 'Помилка створення процедури';
  } finally {
    creating.value = false;
  }
};

const startEdit = (procedure) => {
  editingProcedureId.value = procedure.id;
  editError.value = null;
  editForm.value = {
    name: procedure.name || '',
    category: procedure.category || '',
    duration_minutes: procedure.duration_minutes ?? 30,
    requires_room: !!procedure.requires_room,
    requires_assistant: !!procedure.requires_assistant,
    equipment_id: procedure.equipment_id || '',
  };
};

const cancelEdit = () => {
  editingProcedureId.value = null;
  editError.value = null;
};

const updateProcedure = async (procedure) => {
  savingEdit.value = true;
  editError.value = null;
  try {
    await procedureApi.update(procedure.id, {
      name: editForm.value.name,
      category: editForm.value.category || null,
      duration_minutes: editForm.value.duration_minutes,
      requires_room: editForm.value.requires_room,
      requires_assistant: editForm.value.requires_assistant,
      equipment_id: editForm.value.equipment_id || null,
    });
    await fetchProcedures();
    editingProcedureId.value = null;
  } catch (err) {
    console.error(err);
    editError.value = err.response?.data?.message || 'Не вдалося оновити процедуру';
  } finally {
    savingEdit.value = false;
  }
};

const deleteProcedure = async (procedure) => {
  if (!window.confirm(`Видалити процедуру "${procedure.name}"?`)) return;
  editError.value = null;
  try {
    await procedureApi.delete(procedure.id);
    await fetchProcedures();
  } catch (err) {
    console.error(err);
    editError.value = err.response?.data?.message || 'Не вдалося видалити процедуру';
  }
};

watch(selectedClinicId, async () => {
  await Promise.all([fetchProcedures(), fetchEquipments()]);
});

onMounted(async () => {
  await loadClinics();
  await Promise.all([fetchProcedures(), fetchEquipments()]);
});
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Процедури</h1>
        <p class="text-sm text-slate-400">Налаштування процедур для клінік.</p>
      </div>
      <button
        class="px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400"
        @click="toggleForm"
      >
        {{ showForm ? 'Приховати форму' : 'Нова процедура' }}
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
      <h2 class="text-sm font-semibold text-slate-200">Додати процедуру</h2>

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

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Категорія</label>
          <input
            v-model="form.category"
            type="text"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Тривалість (хв)</label>
          <input
            v-model.number="form.duration_minutes"
            type="number"
            min="5"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          />
        </div>

        <div>
          <label class="block text-xs uppercase text-slate-400 mb-1">Обладнання</label>
          <select
            v-model="form.equipment_id"
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100"
          >
            <option value="">Без обладнання</option>
            <option v-for="equipment in equipments" :key="equipment.id" :value="equipment.id">
              {{ equipment.name }}
            </option>
          </select>
        </div>
      </div>

      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm text-slate-300">
          <input v-model="form.requires_room" type="checkbox" class="rounded border-slate-700 bg-slate-950" />
          Потрібна кімната
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-300">
          <input v-model="form.requires_assistant" type="checkbox" class="rounded border-slate-700 bg-slate-950" />
          Потрібен асистент
        </label>
      </div>

      <div class="flex items-center justify-between gap-3">
        <span v-if="createError" class="text-sm text-red-400">❌ {{ createError }}</span>
        <button
          class="ml-auto px-4 py-2 rounded-lg bg-emerald-500 text-slate-900 text-sm font-semibold hover:bg-emerald-400 disabled:opacity-60"
          :disabled="creating"
          @click="createProcedure"
        >
          {{ creating ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </section>

    <section class="rounded-xl border border-slate-800 bg-slate-900/40 p-4">
      <div v-if="loading" class="text-sm text-slate-400">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-red-400">{{ error }}</div>
      <div v-else-if="editError" class="text-sm text-red-400">{{ editError }}</div>
      <div v-else-if="!procedures.length" class="text-sm text-slate-400">Немає процедур.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-slate-400 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Назва</th>
              <th class="text-left py-2 px-3">Категорія</th>
              <th class="text-left py-2 px-3">Тривалість</th>
              <th class="text-left py-2 px-3">Обладнання</th>
              <th class="text-left py-2 px-3">Вимоги</th>
              <th class="text-right py-2 px-3">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="procedure in procedures" :key="procedure.id" class="border-t border-slate-800">
              <td class="py-2 px-3">
                <input
                  v-if="editingProcedureId === procedure.id"
                  v-model="editForm.name"
                  type="text"
                  class="w-full rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-sm text-slate-100"
                />
                <span v-else class="text-slate-200">{{ procedure.name }}</span>
              </td>
              <td class="py-2 px-3">
                <input
                  v-if="editingProcedureId === procedure.id"
                  v-model="editForm.category"
                  type="text"
                  class="w-full rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-sm text-slate-100"
                />
                <span v-else class="text-slate-400">{{ procedure.category || '—' }}</span>
              </td>
              <td class="py-2 px-3">
                <input
                  v-if="editingProcedureId === procedure.id"
                  v-model.number="editForm.duration_minutes"
                  type="number"
                  min="5"
                  class="w-full rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-sm text-slate-100"
                />
                <span v-else class="text-slate-400">{{ procedure.duration_minutes }} хв</span>
              </td>
              <td class="py-2 px-3">
                <select
                  v-if="editingProcedureId === procedure.id"
                  v-model="editForm.equipment_id"
                  class="w-full rounded-md bg-slate-950 border border-slate-700 px-2 py-1 text-sm text-slate-100"
                >
                  <option value="">Без обладнання</option>
                  <option v-for="equipment in equipments" :key="equipment.id" :value="equipment.id">
                    {{ equipment.name }}
                  </option>
                </select>
                <span v-else class="text-slate-400">
                  {{ equipments.find((e) => e.id === procedure.equipment_id)?.name || '—' }}
                </span>
              </td>
              <td class="py-2 px-3">
                <div v-if="editingProcedureId === procedure.id" class="flex flex-col gap-1 text-xs text-slate-300">
                  <label class="inline-flex items-center gap-2">
                    <input v-model="editForm.requires_room" type="checkbox" class="accent-emerald-500" />
                    Кімната
                  </label>
                  <label class="inline-flex items-center gap-2">
                    <input v-model="editForm.requires_assistant" type="checkbox" class="accent-emerald-500" />
                    Асистент
                  </label>
                </div>
                <span v-else class="text-slate-400">
                  <span v-if="procedure.requires_room">Кімната</span>
                  <span v-if="procedure.requires_room && procedure.requires_assistant"> · </span>
                  <span v-if="procedure.requires_assistant">Асистент</span>
                  <span v-if="!procedure.requires_room && !procedure.requires_assistant">—</span>
                </span>
              </td>
              <td class="py-2 px-3 text-right text-xs">
                <div v-if="editingProcedureId === procedure.id" class="flex justify-end gap-3">
                  <button
                    class="text-emerald-300 hover:text-emerald-200 disabled:opacity-60"
                    :disabled="savingEdit"
                    @click="updateProcedure(procedure)"
                  >
                    {{ savingEdit ? 'Збереження...' : 'Зберегти' }}
                  </button>
                  <button class="text-slate-400 hover:text-slate-200" @click="cancelEdit">Скасувати</button>
                </div>
                <div v-else class="flex justify-end gap-3">
                  <button class="text-emerald-300 hover:text-emerald-200" @click="startEdit(procedure)">Редагувати</button>
                  <button class="text-red-400 hover:text-red-300" @click="deleteProcedure(procedure)">Видалити</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
