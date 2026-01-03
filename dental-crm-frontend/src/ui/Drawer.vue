<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'
import Button from './Button.vue'
import { radiusTokens, colorTokens } from './tokens'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    width?: string
    closable?: boolean
    closeOnEsc?: boolean
    closeOnOutside?: boolean
  }>(),
  {
    title: '',
    width: '420px',
    closable: true,
    closeOnEsc: true,
    closeOnOutside: true
  }
)

const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void; (e: 'close'): void }>()

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleEsc = (event: KeyboardEvent) => {
  if (!props.modelValue || !props.closeOnEsc) return
  if (event.key === 'Escape') close()
}

onMounted(() => {
  document.addEventListener('keydown', handleEsc)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEsc)
})

watch(
  () => props.modelValue,
  (open) => {
    if (typeof document === 'undefined') return
    document.body.classList.toggle('overflow-hidden', open)
  }
)
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[1200] flex items-stretch justify-end"
        :class="colorTokens.overlay"
        @click="closeOnOutside && close()"
      >
        <transition name="slide-in">
          <aside
            v-if="modelValue"
            class="h-full bg-card text-text shadow-2xl border-l border-border flex flex-col"
            :style="{ width }"
            @click.stop
          >
            <header class="flex items-center justify-between px-4 py-3 border-b border-border">
              <div class="space-y-0.5">
                <p class="text-sm text-text/70 uppercase" v-if="title">{{ title }}</p>
                <slot name="header" />
              </div>
              <Button
                v-if="closable"
                variant="ghost"
                size="sm"
                class="rounded-full"
                aria-label="Close"
                @click="close"
              >
                ✕
              </Button>
            </header>

            <section class="flex-1 overflow-y-auto px-4 py-3 custom-scrollbar" :class="radiusTokens.md">
              <slot />
            </section>

            <footer v-if="$slots.footer" class="border-t border-border px-4 py-3">
              <slot name="footer" />
            </footer>
          </aside>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-in-enter-active,
.slide-in-leave-active {
  transition: transform 0.25s ease;
}
.slide-in-enter-from,
.slide-in-leave-to {
  transform: translateX(100%);
}
</style>

