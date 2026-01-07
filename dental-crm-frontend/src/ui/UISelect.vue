<script setup lang="ts">
import { ref, computed } from 'vue'

interface Option {
  value: string | number
  label: string
  disabled?: boolean
}

interface Props {
  modelValue: string | number | null
  options: Option[]
  placeholder?: string
  searchable?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Оберіть...',
  searchable: false,
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | number | null): void
}>()

const searchQuery = ref('')
const isOpen = ref(false)

const filteredOptions = computed(() => {
  if (!props.searchable || !searchQuery.value) {
    return props.options
  }
  const query = searchQuery.value.toLowerCase()
  return props.options.filter((opt) => opt.label.toLowerCase().includes(query))
})

const selectedLabel = computed(() => {
  const selected = props.options.find((opt) => opt.value === props.modelValue)
  return selected?.label || props.placeholder
})

const selectOption = (option: Option) => {
  if (option.disabled) return
  emit('update:modelValue', option.value)
  isOpen.value = false
  searchQuery.value = ''
}

const toggleOpen = () => {
  if (props.disabled) return
  isOpen.value = !isOpen.value
}
</script>

<template>
  <div class="relative">
    <button
      type="button"
      :disabled="disabled"
      :class="[
        'w-full rounded-lg border border-border/80 bg-bg px-3 py-2 text-left text-sm text-text',
        'focus:outline-none focus:ring-2 focus:ring-emerald-500/50',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        'hover:border-border transition-colors',
      ]"
      @click="toggleOpen"
    >
      <span :class="[modelValue ? 'text-text' : 'text-text/60']">
        {{ selectedLabel }}
      </span>
      <span class="float-right text-text/50">▼</span>
    </button>

    <transition name="dropdown">
      <div
        v-if="isOpen"
        class="absolute z-50 mt-1 w-full rounded-lg border border-border bg-card shadow-lg"
      >
        <div v-if="searchable" class="p-2 border-b border-border">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Пошук..."
            class="w-full rounded bg-bg border border-border/60 px-2 py-1 text-sm text-text focus:outline-none focus:ring-1 focus:ring-emerald-500/50"
            @click.stop
          />
        </div>
        <div class="max-h-60 overflow-y-auto">
          <button
            v-for="option in filteredOptions"
            :key="String(option.value)"
            type="button"
            :disabled="option.disabled"
            :class="[
              'w-full px-3 py-2 text-left text-sm transition-colors',
              'hover:bg-card/80',
              modelValue === option.value
                ? 'bg-emerald-500/10 text-emerald-300'
                : 'text-text',
              option.disabled && 'opacity-50 cursor-not-allowed',
            ]"
            @click="selectOption(option)"
          >
            {{ option.label }}
          </button>
          <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-text/60 text-center">
            Нічого не знайдено
          </div>
        </div>
      </div>
    </transition>

    <!-- Click outside to close -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40"
      @click="isOpen = false"
    />
  </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.dropdown-enter-from {
  opacity: 0;
  transform: translateY(-4px);
}
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>

