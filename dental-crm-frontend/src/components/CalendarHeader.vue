<template>
  <div class="flex flex-wrap items-center gap-4">
    <div ref="pickerWrapper" class="relative">
      <input ref="inputRef" type="text" class="sr-only" readonly />
      <button
        type="button"
        class="text-lg font-semibold text-text transition-colors hover:text-text/80"
        @click="openPicker"
      >
        {{ formattedLabel }}
      </button>
      <div
        ref="pickerRef"
        class="absolute left-0 top-full z-20 mt-2"
        v-show="isPickerOpen"
      ></div>
    </div>

    <div class="flex items-center gap-2">
      <button
        type="button"
        class="h-9 w-9 rounded-md border border-border/80 text-lg text-text/80 transition hover:bg-card/80"
        @click="$emit('prev')"
      >
        ‹
      </button>
      <button
        type="button"
        class="h-9 w-9 rounded-md border border-border/80 text-lg text-text/80 transition hover:bg-card/80"
        @click="$emit('next')"
      >
        ›
      </button>
      <button
        type="button"
        class="px-3 py-2 rounded-md border border-emerald-500/60 text-sm text-emerald-200 transition hover:bg-emerald-500/10"
        @click="$emit('today')"
      >
        Сьогодні
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DatePicker from 'tui-date-picker';
import { ensureUkLocale } from '../utils/toastUiLocale';

const props = defineProps({
  currentDate: {
    type: Date,
    required: true,
  },
});

const emit = defineEmits(['select-date', 'prev', 'next', 'today']);

const pickerRef = ref(null);
const inputRef = ref(null);
const pickerWrapper = ref(null);
const isPickerOpen = ref(false);
let pickerInstance = null;

const formatMonthLabel = (date) => {
  const formatter = new Intl.DateTimeFormat('uk-UA', {
    month: 'long',
    year: 'numeric',
  });
  const normalized = formatter.format(date).replace(/\s*р\.?$/u, '').trim();
  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const formattedLabel = computed(() => formatMonthLabel(props.currentDate));

const openPicker = () => {
  isPickerOpen.value = !isPickerOpen.value;
};

const handleSelect = (date) => {
  if (!date) return;
  emit('select-date', new Date(date.getFullYear(), date.getMonth(), 1));
  isPickerOpen.value = false;
};

const handleDocumentClick = (event) => {
  const wrapper = pickerWrapper.value;
  if (!wrapper || wrapper.contains(event.target)) return;
  isPickerOpen.value = false;
};

onMounted(() => {
  ensureUkLocale();

  pickerInstance = new DatePicker(pickerRef.value, {
    date: props.currentDate,
    type: 'month',
    language: 'uk',
    weekStartDay: 'Mon',
    input: {
      element: inputRef.value,
    },
    showAlways: true,
  });

  pickerInstance.on('change', () => {
    handleSelect(pickerInstance.getDate());
  });

  document.addEventListener('click', handleDocumentClick);
});

watch(
  () => props.currentDate,
  (date) => {
    if (!pickerInstance || !date) return;
    pickerInstance.setDate(date);
  }
);

onBeforeUnmount(() => {
  document.removeEventListener('click', handleDocumentClick);
  pickerInstance?.destroy();
});
</script>
