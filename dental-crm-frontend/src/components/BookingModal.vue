<script setup>
import { computed, reactive, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  booking: { type: Object, default: () => ({}) },
  bookingLoading: { type: Boolean, default: false },
  bookingError: { type: String, default: null },
});

const emit = defineEmits(['close', 'submit']);

const localBooking = reactive({ ...props.booking });

watch(
    () => props.booking,
    (val) => {
      Object.assign(localBooking, val || {});
    },
    { deep: true }
);

const formattedStart = computed(() => localBooking.start ? new Date(localBooking.start).toLocaleString() : '');

const handleSubmit = () => {
  emit('submit', { ...localBooking });
};
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
    <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-xl shadow-2xl overflow-hidden">
      <div class="p-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
        <div>
          <p class="text-white font-semibold">Створити запис</p>
          <p class="text-xs text-slate-400">
            {{ formattedStart }}
          </p>
        </div>
        <button class="text-slate-400 hover:text-white text-xl" @click="emit('close')">
          <X />
        </button>
      </div>

      <div class="p-4 space-y-3">
        <div v-if="bookingError" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3">
          {{ bookingError }}
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1 block">
            <span class="text-xs text-slate-400">ID пацієнта</span>
            <input
                v-model="localBooking.patient_id"
                type="number"
                class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
                placeholder="Напр. 42"
            />
            <p class="text-xs text-slate-500">Залиште порожнім для гостя</p>
          </label>

          <label class="space-y-1 block">
            <span class="text-xs text-slate-400">Waitlist entry ID</span>
            <input
                v-model="localBooking.waitlist_entry_id"
                type="number"
                class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
                placeholder="Напр. 12"
            />
            <p class="text-xs text-slate-500">Опційно</p>
          </label>
        </div>

        <label class="space-y-1 block">
          <span class="text-xs text-slate-400">Коментар</span>
          <textarea
              v-model="localBooking.comment"
              rows="3"
              class="w-full bg-slate-950 border border-slate-700 rounded px-3 py-2 text-white"
              placeholder="Скарги, побажання, особливі вимоги..."
          ></textarea>
        </label>
      </div>

      <div class="p-4 border-t border-slate-800 flex justify-end gap-2">
        <button
            class="px-4 py-2 rounded border border-slate-700 text-slate-200 hover:text-white"
            @click="emit('close')"
        >
          Скасувати
        </button>
        <button
            class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-60"
            :disabled="bookingLoading"
            @click="handleSubmit"
        >
          {{ bookingLoading ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </div>
  </div>
</template>