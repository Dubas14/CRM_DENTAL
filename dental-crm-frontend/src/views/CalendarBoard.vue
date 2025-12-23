<template>
  <div class="p-6 space-y-4">
    <CalendarComponent />
    <ToastContainer />
  </div>
</template>

<script setup>
import CalendarComponent from '../components/CalendarComponent.vue';
import ToastContainer from '../components/ToastContainer.vue';
import '../assets/css/calendar.css';
</script>