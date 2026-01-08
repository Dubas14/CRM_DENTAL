<script setup lang="ts">
import { ref, watch } from 'vue'
import { ChevronDown } from 'lucide-vue-next'

interface Props {
  title: string
  open?: boolean
  hasUnsavedChanges?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  hasUnsavedChanges: false
})

const isOpen = ref(props.open)

watch(() => props.open, (newVal) => {
  isOpen.value = newVal
})

const toggle = () => {
  isOpen.value = !isOpen.value
}
</script>

<template>
  <div class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 border border-border overflow-hidden">
    <button
      type="button"
      class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-card/60 transition-colors"
      @click="toggle"
    >
      <div class="flex items-center gap-3">
        <h3 class="text-lg font-semibold text-text">{{ title }}</h3>
        <span
          v-if="hasUnsavedChanges"
          class="w-2 h-2 rounded-full bg-red-500"
          title="Є незбережені зміни"
        ></span>
      </div>
      <ChevronDown
        :size="20"
        class="text-text/70 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
      />
    </button>
    <transition
      enter-active-class="transition-all duration-300 ease-out"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-[5000px] opacity-100"
      leave-active-class="transition-all duration-300 ease-in"
      leave-from-class="max-h-[5000px] opacity-100"
      leave-to-class="max-h-0 opacity-0"
    >
      <div v-if="isOpen" class="overflow-hidden">
        <div class="px-6 pb-6 pt-2">
          <slot />
        </div>
      </div>
    </transition>
  </div>
</template>
