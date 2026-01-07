<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Button from './Button.vue'
import { radiusTokens, colorTokens } from './tokens'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    width?: string
    maxWidth?: string
    position?: 'right' | 'center'
    resizable?: boolean
    closable?: boolean
    closeOnEsc?: boolean
    closeOnOutside?: boolean
  }>(),
  {
    title: '',
    width: '420px',
    maxWidth: '90vw',
    position: 'right',
    resizable: false,
    closable: true,
    closeOnEsc: true,
    closeOnOutside: true
  }
)

const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void; (e: 'close'): void }>()

const currentWidth = ref(props.width)
const isResizing = ref(false)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const handleEsc = (event: KeyboardEvent) => {
  if (!props.modelValue || !props.closeOnEsc) return
  if (event.key === 'Escape') close()
}

// Resize handlers
const startResize = (e: MouseEvent) => {
  if (!props.resizable) return
  isResizing.value = true
  e.preventDefault()
  document.addEventListener('mousemove', doResize)
  document.addEventListener('mouseup', stopResize)
}

const doResize = (e: MouseEvent) => {
  if (!isResizing.value) return
  
  if (props.position === 'center') {
    // For center: calculate from center of screen
    const screenWidth = window.innerWidth
    const centerX = screenWidth / 2
    const newHalfWidth = Math.abs(e.clientX - centerX)
    const newWidth = Math.min(Math.max(newHalfWidth * 2, 320), screenWidth * 0.95)
    currentWidth.value = `${newWidth}px`
  } else {
    // For right: calculate from right edge
    const newWidth = Math.min(Math.max(window.innerWidth - e.clientX, 320), window.innerWidth * 0.95)
    currentWidth.value = `${newWidth}px`
  }
}

const stopResize = () => {
  isResizing.value = false
  document.removeEventListener('mousemove', doResize)
  document.removeEventListener('mouseup', stopResize)
}

onMounted(() => {
  document.addEventListener('keydown', handleEsc)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleEsc)
  document.removeEventListener('mousemove', doResize)
  document.removeEventListener('mouseup', stopResize)
})

watch(
  () => props.modelValue,
  (open) => {
    if (typeof document === 'undefined') return
    document.body.classList.toggle('overflow-hidden', open)
    if (open) {
      currentWidth.value = props.width
    }
  }
)
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[1200] flex"
        :class="[
          colorTokens.overlay,
          position === 'center' ? 'items-center justify-center' : 'items-stretch justify-end'
        ]"
        @click="closeOnOutside && close()"
      >
        <transition :name="position === 'center' ? 'scale' : 'slide-in'">
          <aside
            v-if="modelValue"
            class="bg-card text-text shadow-2xl flex flex-col relative"
            :class="[
              position === 'center' 
                ? 'rounded-xl border border-border max-h-[90vh]' 
                : 'h-full border-l border-border'
            ]"
            :style="{ width: currentWidth, maxWidth }"
            @click.stop
          >
            <!-- Resize handle -->
            <div
              v-if="resizable"
              class="absolute top-0 left-0 w-1 h-full cursor-ew-resize hover:bg-primary/30 transition-colors"
              @mousedown="startResize"
            />
            
            <header class="flex items-center justify-between px-4 py-3 border-b border-border shrink-0">
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

            <footer v-if="$slots.footer" class="border-t border-border px-4 py-3 shrink-0">
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

.scale-enter-active,
.scale-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.scale-enter-from,
.scale-leave-to {
  transform: scale(0.95);
  opacity: 0;
}
</style>
