<template>
  <EventEditModal
    v-if="open"
    :model-value="open"
    :event-data="event"
    :doctors="doctors"
    :default-doctor-id="defaultDoctorId"
    @update:modelValue="handleUpdate"
    @save="handleSave"
  />
</template>

<script setup lang="ts">
import EventEditModal from './EventEditModal.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false
  },
  event: {
    type: Object,
    default: () => ({})
  },
  doctors: {
    type: Array,
    default: () => []
  },
  defaultDoctorId: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['save', 'close'])

const handleUpdate = (isOpen) => {
  if (!isOpen && props.open) {
    emit('close')
  }
}

const handleSave = (payload) => {
  emit('save', payload)
}
</script>
