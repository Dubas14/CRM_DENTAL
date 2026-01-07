<script setup lang="ts">
import { ref } from 'vue'

interface Props {
  modelValue: {
    from: string | null
    to: string | null
  }
  placeholder?: {
    from?: string
    to?: string
  }
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: () => ({
    from: 'Від',
    to: 'До'
  })
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: { from: string | null; to: string | null }): void
}>()

const from = ref(props.modelValue.from || '')
const to = ref(props.modelValue.to || '')

const updateFrom = (value: string) => {
  from.value = value
  emit('update:modelValue', { from: value || null, to: to.value || null })
}

const updateTo = (value: string) => {
  to.value = value
  emit('update:modelValue', { from: from.value || null, to: value || null })
}
</script>

<template>
  <div class="flex items-center gap-2">
    <input
      :value="from"
      type="date"
      :placeholder="placeholder.from"
      class="rounded-lg border border-border/80 bg-bg px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
      @input="updateFrom(($event.target as HTMLInputElement).value)"
    />
    <span class="text-text/60">—</span>
    <input
      :value="to"
      type="date"
      :placeholder="placeholder.to"
      class="rounded-lg border border-border/80 bg-bg px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
      @input="updateTo(($event.target as HTMLInputElement).value)"
    />
  </div>
</template>
