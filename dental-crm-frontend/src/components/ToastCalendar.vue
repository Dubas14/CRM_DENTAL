<template>
  <div ref="calendarEl" class="h-full w-full"></div>
</template>

<script setup>

import { ref, onMounted, onBeforeUnmount } from 'vue'
import Calendar from '@toast-ui/calendar'
import '@toast-ui/calendar/dist/toastui-calendar.css'
import '../assets/css/toast-calendar-theme.css'

const DAY_NAMES = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
const HOUR_FORMAT = 'HH:mm';

const ukLocale = {
  week: {
    dayNames: DAY_NAMES,
    narrowDayNames: DAY_NAMES,
  },
  month: {
    dayNames: DAY_NAMES,
  },
  titles: {
    today: 'Сьогодні',
    day: 'День',
    week: 'Тиждень',
    month: 'Місяць',
  },
  time: {
    am: '',
    pm: '',
  },
  allDay: 'Весь день',
};

const getDayIndex = (dayInfo) => {
  if (!dayInfo) return null;

  const candidateDate = dayInfo.date ?? dayInfo.start;
  if (candidateDate) {
    const date = candidateDate instanceof Date ? candidateDate : new Date(candidateDate);
    if (!Number.isNaN(date.getTime())) {
      return date.getDay();
    }
  }

  const directIndex = dayInfo.day ?? dayInfo.dayOfWeek ?? dayInfo.dayIndex;
  if (typeof directIndex === 'number') {
    return directIndex;
  }

  const label = dayInfo.dayName ?? dayInfo.label ?? dayInfo.name;
  if (label) {
    const normalized = label.toLowerCase();
    const matchedIndex = DAY_NAMES.findIndex((name) => name.toLowerCase() === normalized);
    return matchedIndex >= 0 ? matchedIndex : null;
  }

  return null;
};

const getDayLabel = (dayInfo) => {
  const index = getDayIndex(dayInfo);
  if (index !== null && index !== undefined) {
    return DAY_NAMES[index];
  }

  return dayInfo?.dayName ?? dayInfo?.label ?? dayInfo?.name ?? '';
};

const formatTime = (time) => {
  const date = time?.toDate ? time.toDate() : time;
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
    return '';
  }

  const hours = `${date.getHours()}`.padStart(2, '0');
  const minutes = `${date.getMinutes()}`.padStart(2, '0');
  return `${hours}:${minutes}`;
};

const formatTemplateTime = ({ time }) => formatTime(time);

defineExpose({
  next: () => calendarInstance?.next(),
  prev: () => calendarInstance?.prev(),
  today: () => calendarInstance?.today(),
  setDate: (date) => calendarInstance?.setDate?.(date),
  changeView: (view) => calendarInstance?.changeView(view),
  getDate: () => calendarInstance?.getDate?.(),
  updateEvent: (eventId, calendarId, changes) => calendarInstance?.updateEvent?.(eventId, calendarId, changes),
  createEvents: (events) => calendarInstance?.createEvents?.(events),
  clear: () => calendarInstance?.clear?.(),
  getDateRangeStart: () => calendarInstance?.getDateRangeStart?.(),
  getDateRangeEnd: () => calendarInstance?.getDateRangeEnd?.(),
})

const emit = defineEmits(['selectDateTime', 'clickEvent', 'beforeUpdateEvent'])

const calendarEl = ref(null)
let calendarInstance = null

onMounted(() => {
  calendarInstance = new Calendar(calendarEl.value, {
    defaultView: 'week',
    height: '100%',
    locale: ukLocale,
    isReadOnly: false,
    usageStatistics: false,
    week: {
      startDayOfWeek: 1,
      hourStart: 8,
      hourEnd: 22,
      dayNames: DAY_NAMES,
      hourFormat: HOUR_FORMAT,
      eventView: ['time'],
      taskView: false,
    },
    day: {
      hourFormat: HOUR_FORMAT,
    },
    month: {
      dayNames: DAY_NAMES,
    },
    templates: {
      weekDayname: (dayname) => `<span>${getDayLabel(dayname)}</span>`,
      monthDayname: (dayname) => `<span>${getDayLabel(dayname)}</span>`,
      milestoneTitle: () => 'Етапи',
      taskTitle: () => 'Завдання',
      alldayTitle: () => 'Весь день',
      timegridDisplayPrimaryTime: formatTemplateTime,
      timegridDisplayTime: formatTemplateTime,
    },
    useDetailPopup: false,
    useFormPopup: false,
  });

  calendarInstance.on('selectDateTime', (info) => {
    emit('selectDateTime', info)
  })

  calendarInstance.on('clickEvent', (info) => {
    emit('clickEvent', info)
  })

  calendarInstance.on('beforeUpdateEvent', (info) => {
    emit('beforeUpdateEvent', info)
  })

})

onBeforeUnmount(() => {
  calendarInstance?.destroy()
})
</script>
