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
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-text/20 dark:bg-bg/50 p-4">
    <div class="w-full max-w-lg bg-card rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40 shadow-2xl overflow-hidden">
      <div class="p-4 bg-bg border-b border-border flex items-center justify-between">
        <div>
          <p class="text-text font-semibold">Створити запис</p>
          <p class="text-xs text-text/70">
            {{ formattedStart }}
          </p>
        </div>
        <button class="text-text/70 hover:text-text text-xl" @click="emit('close')">
          <X />
        </button>
      </div>

      <div class="p-4 space-y-3">
        <div v-if="bookingError" class="text-sm text-red-400 bg-red-900/20 border border-red-700/40 rounded-lg p-3">
          {{ bookingError }}
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="space-y-1 block">
            <span class="text-xs text-text/70">ID пацієнта</span>
            <input
                v-model="localBooking.patient_id"
                type="number"
                class="w-full bg-bg border border-border/80 rounded px-3 py-2 text-text"
                placeholder="Напр. 42"
            />
            <p class="text-xs text-text/60">Залиште порожнім для гостя</p>
          </label>

          <label class="space-y-1 block">
            <span class="text-xs text-text/70">Waitlist entry ID</span>
            <input
                v-model="localBooking.waitlist_entry_id"
                type="number"
                class="w-full bg-bg border border-border/80 rounded px-3 py-2 text-text"
                placeholder="Напр. 12"
            />
            <p class="text-xs text-text/60">Опційно</p>
          </label>
        </div>

        <label class="space-y-1 block">
          <span class="text-xs text-text/70">Коментар</span>
          <textarea
              v-model="localBooking.comment"
              rows="3"
              class="w-full bg-bg border border-border/80 rounded px-3 py-2 text-text"
              placeholder="Скарги, побажання, особливі вимоги..."
          ></textarea>
        </label>
      </div>

      <div class="p-4 border-t border-border flex justify-end gap-2">
        <button
            class="px-4 py-2 rounded border border-border/80 text-text/90 hover:text-text"
            @click="emit('close')"
        >
          Скасувати
        </button>
        <button
            class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-text disabled:opacity-60"
            :disabled="bookingLoading"
            @click="handleSubmit"
        >
          {{ bookingLoading ? 'Створення...' : 'Створити' }}
        </button>
      </div>
    </div>
  </div>
</template>