<template>
  <div class="pointer-events-none absolute left-0 right-0 flex items-center gap-2" :style="{ top: `${top}px` }">
    <span class="-ml-1 h-2 w-2 rounded-full bg-emerald-400"></span>
    <span class="h-px flex-1 bg-emerald-400/80"></span>
    <span class="rounded bg-bg px-2 py-0.5 text-[10px] text-emerald-200">{{ label }}</span>
  </div>
</template>

<script setup>
defineProps({
  top: {
    type: Number,
    required: true,
  },
  label: {
    type: String,
    default: '',
  },
})
</script>
