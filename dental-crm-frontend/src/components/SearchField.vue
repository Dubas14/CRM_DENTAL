<script setup lang="ts">
import { ref, watch } from 'vue'
import { debounce } from 'lodash-es'

interface Props {
  modelValue: string
  placeholder?: string
  debounceMs?: number
  autoFocus?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Пошук...',
  debounceMs: 300,
  autoFocus: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  search: [value: string]
}>()

const localValue = ref(props.modelValue)

const debouncedEmit = debounce((value: string) => {
  emit('search', value)
}, props.debounceMs)

watch(
  () => props.modelValue,
  (newVal) => {
    localValue.value = newVal
  }
)

watch(localValue, (newVal) => {
  emit('update:modelValue', newVal)
  debouncedEmit(newVal)
})
</script>

<template>
  <div class="flex items-center gap-2">
    <label :for="`search-field-${$attrs.id || 'default'}`" class="sr-only">Пошук</label>
    <input
      :id="`search-field-${$attrs.id || 'default'}`"
      v-model="localValue"
      type="text"
      :placeholder="placeholder"
      :autofocus="autoFocus"
      class="w-full md:w-64 max-w-full rounded-lg bg-card border border-border/80 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
      v-bind="$attrs"
    />
  </div>
</template>
