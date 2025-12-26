<template>
  <EventEditModal
      v-if="open"
      :model-value="open"
      :event-data="event"
      @update:modelValue="handleUpdate"
      @save="handleSave"
  />
</template>

<script setup>
import EventEditModal from './EventEditModal.vue';

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  event: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['save', 'close']);

const handleUpdate = (isOpen) => {
  if (!isOpen && props.open) {
    emit('close');
  }
};

const handleSave = (payload) => {
  emit('save', payload);
};
</script>
