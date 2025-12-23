<script setup>
import { computed } from 'vue';

import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import ukLocale from '@fullcalendar/core/locales/uk';

import { useAuth } from '../composables/useAuth';
import { useCalendar } from '../composables/useCalendar';
import BookingModal from './BookingModal.vue';

useAuth();

const {
  calendarRef,
  events,
  availabilityBgEvents,
  viewMode,
  selectedDoctorId,
  selectedProcedureId,
  selectedEquipmentId,
  selectedRoomId,
  selectedAssistantId,
  isFollowUp,
  allowSoftConflicts,
  doctors,
  procedures,
  rooms,
  equipments,
  loading,
  loadingSlots,
  error,
  booking,
  isBookingOpen,
  bookingLoading,
  bookingError,
  closeBooking,
  createAppointment,
  handleSelect,
  handleEventClick,
  handleEventMoveResize,
  showDragAvailability,
  hideDragAvailability,
  selectAllow,
  handleDatesSet,
} = useCalendar();

const calendarOptions = computed(() => ({
  plugins: [timeGridPlugin, dayGridPlugin, interactionPlugin],
  initialView: viewMode.value,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridDay,timeGridWeek,dayGridMonth',
  },

  timeZone: 'local',

  // ✅ UA locale
  locales: [ukLocale],
  locale: 'uk',

  height: 'auto',
  nowIndicator: true,
  selectable: true,
  editable: true,
  eventResizableFromStart: false,

  // ✅ 24h grid + scroll to morning
  slotMinTime: '00:00:00',
  slotMaxTime: '24:00:00',
  scrollTime: '07:00:00',

  // ✅ slot labels like 07:00, 07:30 (no AM/PM)
  slotLabelFormat: {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  },

  slotDuration: '00:30:00',
  allDaySlot: false,
  weekends: true,

  events: [...availabilityBgEvents.value, ...events.value],

  selectAllow,
  select: handleSelect,
  eventClick: handleEventClick,

  eventDragStart: async (info) => {
    await showDragAvailability(info.event);
  },
  eventDragStop: async () => {
    await hideDragAvailability();
  },
  eventDrop: async (info) => {
    await handleEventMoveResize(info, 'drop');
  },
  eventResize: async (info) => {
    await handleEventMoveResize(info, 'resize');
  },

  datesSet: handleDatesSet,
}));

const onBookingSubmit = (payload) => {
  createAppointment(payload);
};
</script>

<template>
  <div class="space-y-4">
    <!-- Можеш лишити свої фільтри як були — тут просто мінімально -->
    <div v-if="error" class="rounded-lg bg-red-50 text-red-700 p-3">
      {{ error }}
    </div>

    <div v-if="loading || loadingSlots" class="text-slate-500">
      Завантаження…
    </div>

    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
      <FullCalendar ref="calendarRef" :options="calendarOptions" />
    </div>

    <BookingModal
        v-if="isBookingOpen"
        :booking="booking"
        :loading="bookingLoading"
        :error="bookingError"
        @close="closeBooking"
        @submit="onBookingSubmit"
    />
  </div>
</template>
