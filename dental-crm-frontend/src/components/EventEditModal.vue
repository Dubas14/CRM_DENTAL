<template>
  <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/40" @click="close"></div>
    <div class="relative w-full max-w-2xl rounded-2xl bg-card p-6 shadow-xl">
      <div class="flex items-center justify-between gap-4">
        <h2 class="text-xl font-semibold text-text">
          {{ isEditMode ? 'Редагування запису' : 'Новий запис' }}
        </h2>
        <button
          type="button"
          class="rounded-md border border-border/80 px-3 py-1 text-sm text-text/80 hover:bg-card/80"
          @click="close"
        >
          Закрити
        </button>
      </div>

      <div class="mt-6 grid gap-6">
        <div>
          <label class="text-sm text-text/70">Назва</label>
          <input
            v-model="form.title"
            type="text"
            class="mt-2 w-full rounded-lg border border-border/80 bg-bg px-3 py-2 text-sm"
            placeholder="Наприклад, консультація"
          />
        </div>

        <div class="grid gap-6 md:grid-cols-2">
          <div>
            <label class="text-sm text-text/70">Дата</label>
            <input ref="dateInputRef" type="text" class="sr-only" readonly />
            <div ref="datePickerRef" class="mt-3"></div>
          </div>
          <div class="grid gap-6">
            <div>
              <label class="text-sm text-text/70">Початок</label>
              <div ref="startTimeRef" class="mt-3"></div>
            </div>
            <div>
              <label class="text-sm text-text/70">Кінець</label>
              <div ref="endTimeRef" class="mt-3"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-end gap-3">
        <button
          type="button"
          class="px-4 py-2 rounded-lg border border-border/80 text-sm text-text/80 hover:bg-card/80"
          @click="close"
        >
          Скасувати
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-white hover:bg-emerald-400"
          @click="save"
        >
          Зберегти
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DatePicker from 'tui-date-picker';
import TimePicker from 'tui-time-picker';
import { ensureUkLocale } from '../utils/toastUiLocale';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  eventData: {
    type: Object,
    default: () => ({}),
  },
  minuteStep: {
    type: Number,
    default: 10,
    validator: (value) => [5, 10, 15].includes(value),
  },
});

const emit = defineEmits(['update:modelValue', 'save']);

const datePickerRef = ref(null);
const dateInputRef = ref(null);
const startTimeRef = ref(null);
const endTimeRef = ref(null);

let datePickerInstance = null;
let startTimePicker = null;
let endTimePicker = null;

const form = ref({
  title: '',
  date: new Date(),
  startTime: { hour: 9, minute: 0 },
  endTime: { hour: 10, minute: 0 },
});

const isEditMode = computed(() => Boolean(props.eventData?.id));

const syncFromProps = () => {
  const start = props.eventData?.start ? new Date(props.eventData.start) : new Date();
  const end = props.eventData?.end ? new Date(props.eventData.end) : new Date(start.getTime() + 30 * 60000);

  form.value = {
    title: props.eventData?.title || '',
    date: new Date(start.getFullYear(), start.getMonth(), start.getDate()),
    startTime: { hour: start.getHours(), minute: start.getMinutes() },
    endTime: { hour: end.getHours(), minute: end.getMinutes() },
  };

  datePickerInstance?.setDate(form.value.date);
  startTimePicker?.setTime(form.value.startTime.hour, form.value.startTime.minute);
  endTimePicker?.setTime(form.value.endTime.hour, form.value.endTime.minute);
};

const close = () => {
  emit('update:modelValue', false);
};

const save = () => {
  const { date, startTime, endTime, title } = form.value;
  const startDate = new Date(date);
  startDate.setHours(startTime.hour, startTime.minute, 0, 0);
  const endDate = new Date(date);
  endDate.setHours(endTime.hour, endTime.minute, 0, 0);

  emit('save', {
    ...props.eventData,
    title,
    start: startDate,
    end: endDate,
  });
};

onMounted(() => {
  ensureUkLocale();

  datePickerInstance = new DatePicker(datePickerRef.value, {
    date: form.value.date,
    language: 'uk',
    weekStartDay: 'Mon',
    input: {
      element: dateInputRef.value,
    },
    showAlways: true,
    autoClose: false,
  });

  datePickerInstance.on('change', () => {
    const selectedDate = datePickerInstance.getDate();
    if (selectedDate) {
      form.value.date = selectedDate;
    }
  });

  startTimePicker = new TimePicker(startTimeRef.value, {
    initialHour: form.value.startTime.hour,
    initialMinute: form.value.startTime.minute,
    inputType: 'spinbox',
    showMeridiem: false,
    minuteStep: props.minuteStep,
    language: 'uk',
  });

  startTimePicker.on('change', (evt) => {
    form.value.startTime = { hour: evt.hour, minute: evt.minute };
  });

  endTimePicker = new TimePicker(endTimeRef.value, {
    initialHour: form.value.endTime.hour,
    initialMinute: form.value.endTime.minute,
    inputType: 'spinbox',
    showMeridiem: false,
    minuteStep: props.minuteStep,
    language: 'uk',
  });

  endTimePicker.on('change', (evt) => {
    form.value.endTime = { hour: evt.hour, minute: evt.minute };
  });
});

watch(
  () => props.eventData,
  () => {
    if (!props.modelValue) return;
    syncFromProps();
  },
  { deep: true }
);

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      syncFromProps();
    }
  }
);

onBeforeUnmount(() => {
  datePickerInstance?.destroy();
  startTimePicker?.destroy();
  endTimePicker?.destroy();
});
</script>
