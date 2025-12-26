<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import apiClient from '../services/apiClient';
import { useAuth } from '../composables/useAuth';
import { usePermissions } from '../composables/usePermissions';

const props = defineProps({
  isOpen: Boolean,
  initialName: String,
  initialPhone: String
});

const emit = defineEmits(['close', 'created']);

const { user } = useAuth();
const { isDoctor } = usePermissions();

const loading = ref(false);
const error = ref(null);
const clinics = ref([]);

const form = ref({
  clinic_id: '',
  full_name: '',
  phone: '',
  birth_date: '',
  email: '',
  address: '',
  note: ''
});

// Автозаповнення при відкритті
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    form.value.full_name = props.initialName || '';
    form.value.phone = props.initialPhone || '';
    loadClinics();
  }
});

const doctorClinicId = computed(() => user.value?.doctor?.clinic_id);

const loadClinics = async () => {
  // Якщо це лікар, він може створювати тільки у своїй клініці
  if (isDoctor.value && doctorClinicId.value) {
    form.value.clinic_id = doctorClinicId.value;
    return; // Не вантажимо список
  }

  // Якщо адмін - вантажимо список
  try {
    const { data } = await apiClient.get('/clinics');
    clinics.value = data;
    // Якщо клініка одна - вибираємо її
    if (data.length === 1) form.value.clinic_id = data[0].id;
  } catch (e) {
    console.error(e);
  }
};

const validatePhone = (event) => {
  let val = event.target.value.replace(/[^0-9+\-() ]/g, '');
  form.value.phone = val;
  event.target.value = val;
};

const submit = async () => {
  if (!form.value.full_name) {
    error.value = "Введіть ПІБ";
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const { data } = await apiClient.post('/patients', form.value);
    emit('created', data); // Повертаємо створеного пацієнта батьку
    emit('close');
    // Очистка (крім клініки)
    form.value = { ...form.value, full_name: '', phone: '', email: '', note: '' };
  } catch (e) {
    if (e.response?.data?.errors) {
      error.value = Object.values(e.response.data.errors).flat().join(', ');
    } else {
      error.value = e.response?.data?.message || 'Помилка створення';
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-[60] flex items-center justify-center bg-text/20 dark:bg-bg/50 backdrop-blur-sm p-4">
    <div class="bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">

      <div class="bg-bg p-4 border-b border-border flex justify-between items-center">
        <h3 class="text-lg font-bold text-text">Нова анкета пацієнта</h3>
        <button @click="$emit('close')" class="text-text/70 hover:text-text">✕</button>
      </div>

      <div class="p-6 overflow-y-auto custom-scrollbar space-y-4">
        <div v-if="error" class="bg-red-900/30 border border-red-500/30 text-red-300 p-3 rounded text-sm">
          {{ error }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">

          <div v-if="!isDoctor && clinics.length > 0">
            <label class="block text-xs uppercase text-text/70 mb-1">Клініка</label>
            <select v-model="form.clinic_id" required class="w-full bg-bg border border-border/80 rounded p-2 text-text/90">
              <option value="" disabled>Оберіть клініку</option>
              <option v-for="c in clinics" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">ПІБ <span class="text-red-400">*</span></label>
            <input v-model="form.full_name" type="text" class="w-full bg-bg border border-border/80 rounded p-2 text-text/90" placeholder="Іванов Іван Іванович">
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase text-text/70 mb-1">Телефон</label>
              <input v-model="form.phone" @input="validatePhone" type="text" class="w-full bg-bg border border-border/80 rounded p-2 text-text/90" placeholder="+380...">
            </div>
            <div>
              <label class="block text-xs uppercase text-text/70 mb-1">Дата народження</label>
              <input v-model="form.birth_date" type="date" class="w-full bg-bg border border-border/80 rounded p-2 text-text/90">
            </div>
          </div>

          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Примітка</label>
            <textarea v-model="form.note" rows="2" class="w-full bg-bg border border-border/80 rounded p-2 text-text/90"></textarea>
          </div>

          <div class="pt-2 flex justify-end gap-3">
            <button type="button" @click="$emit('close')" class="px-4 py-2 text-text/70 hover:text-text">Скасувати</button>
            <button type="submit" :disabled="loading" class="bg-emerald-600 text-text px-6 py-2 rounded hover:bg-emerald-500 disabled:opacity-50">
              {{ loading ? 'Збереження...' : 'Створити анкету' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>