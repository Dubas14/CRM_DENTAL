<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { UIButton } from '../../ui'

interface SurfaceState {
  M?: boolean // Mesial (Медіальна)
  D?: boolean // Distal (Дистальна)
  O?: boolean // Occlusal (Оклюзійна)
  B?: boolean // Buccal/Vestibular (Вестибулярна)
  L?: boolean // Lingual (Язикова)
}

const props = defineProps<{
  modelValue: boolean
  toothNumber: number
  initialSurfaces?: string[] | SurfaceState | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'save', surfaces: string[]): void
}>()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const surfaces = ref<SurfaceState>({
  M: false,
  D: false,
  O: false,
  B: false,
  L: false
})

const surfaceLabels = {
  M: 'Медіальна',
  D: 'Дистальна',
  O: 'Оклюзійна',
  B: 'Вестибулярна',
  L: 'Язикова'
}

const toggleSurface = (key: keyof SurfaceState) => {
  surfaces.value[key] = !surfaces.value[key]
}

const selectedSurfaces = computed(() => {
  return Object.entries(surfaces.value)
    .filter(([_, selected]) => selected)
    .map(([key]) => key)
})

const save = () => {
  emit('save', selectedSurfaces.value)
  open.value = false
}

watch(
  () => props.initialSurfaces,
  (newSurfaces) => {
    if (newSurfaces) {
      if (Array.isArray(newSurfaces)) {
        // Reset and set selected
        surfaces.value = { M: false, D: false, O: false, B: false, L: false }
        newSurfaces.forEach((s) => {
          if (s in surfaces.value) {
            surfaces.value[s as keyof SurfaceState] = true
          }
        })
      } else if (typeof newSurfaces === 'object') {
        surfaces.value = { ...surfaces.value, ...newSurfaces }
      }
    } else {
      surfaces.value = { M: false, D: false, O: false, B: false, L: false }
    }
  },
  { immediate: true }
)

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen && props.initialSurfaces) {
      if (Array.isArray(props.initialSurfaces)) {
        surfaces.value = { M: false, D: false, O: false, B: false, L: false }
        props.initialSurfaces.forEach((s) => {
          if (s in surfaces.value) {
            surfaces.value[s as keyof SurfaceState] = true
          }
        })
      }
    }
  }
)
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
        @click.self="open = false"
      >
        <div
          class="w-full max-w-md rounded-2xl bg-card text-text shadow-2xl border border-border"
        >
          <div class="p-6 border-b border-border flex items-center justify-between">
            <h2 class="text-xl font-semibold">Поверхні зуба {{ toothNumber }}</h2>
            <button
              type="button"
              class="text-text/70 hover:text-text transition"
              @click="open = false"
            >
              ✕
            </button>
          </div>

          <div class="p-6 space-y-6">
            <!-- Tooth Diagram -->
            <div class="flex justify-center">
              <svg width="200" height="240" viewBox="0 0 200 240" class="border border-border rounded">
                <!-- Tooth shape -->
                <path
                  d="M50,40 Q50,20 100,20 Q150,20 150,40 L150,180 Q150,200 100,200 Q50,200 50,180 Z"
                  fill="#f3f4f6"
                  stroke="#9ca3af"
                  stroke-width="2"
                />

                <!-- Surfaces -->
                <!-- Mesial (Left) -->
                <rect
                  x="50"
                  y="40"
                  width="30"
                  height="140"
                  :fill="surfaces.M ? '#22c55e' : '#e5e7eb'"
                  :stroke="surfaces.M ? '#16a34a' : '#9ca3af'"
                  stroke-width="2"
                  class="cursor-pointer"
                  @click="toggleSurface('M')"
                />

                <!-- Distal (Right) -->
                <rect
                  x="120"
                  y="40"
                  width="30"
                  height="140"
                  :fill="surfaces.D ? '#22c55e' : '#e5e7eb'"
                  :stroke="surfaces.D ? '#16a34a' : '#9ca3af'"
                  stroke-width="2"
                  class="cursor-pointer"
                  @click="toggleSurface('D')"
                />

                <!-- Occlusal (Top) -->
                <rect
                  x="80"
                  y="40"
                  width="40"
                  height="30"
                  :fill="surfaces.O ? '#22c55e' : '#e5e7eb'"
                  :stroke="surfaces.O ? '#16a34a' : '#9ca3af'"
                  stroke-width="2"
                  class="cursor-pointer"
                  @click="toggleSurface('O')"
                />

                <!-- Buccal/Vestibular (Front) -->
                <rect
                  x="80"
                  y="70"
                  width="40"
                  height="60"
                  :fill="surfaces.B ? '#22c55e' : '#e5e7eb'"
                  :stroke="surfaces.B ? '#16a34a' : '#9ca3af'"
                  stroke-width="2"
                  class="cursor-pointer"
                  @click="toggleSurface('B')"
                />

                <!-- Lingual (Back) -->
                <rect
                  x="80"
                  y="130"
                  width="40"
                  height="60"
                  :fill="surfaces.L ? '#22c55e' : '#e5e7eb'"
                  :stroke="surfaces.L ? '#16a34a' : '#9ca3af'"
                  stroke-width="2"
                  class="cursor-pointer"
                  @click="toggleSurface('L')"
                />

                <!-- Labels -->
                <text x="65" y="120" text-anchor="middle" class="text-xs fill-text/60">M</text>
                <text x="135" y="120" text-anchor="middle" class="text-xs fill-text/60">D</text>
                <text x="100" y="60" text-anchor="middle" class="text-xs fill-text/60">O</text>
                <text x="100" y="100" text-anchor="middle" class="text-xs fill-text/60">B</text>
                <text x="100" y="160" text-anchor="middle" class="text-xs fill-text/60">L</text>
              </svg>
            </div>

            <!-- Surface List -->
            <div class="space-y-2">
              <p class="text-sm font-semibold text-text/90 mb-2">Обрані поверхні:</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="(label, key) in surfaceLabels"
                  :key="key"
                  type="button"
                  class="px-3 py-2 rounded-lg border transition"
                  :class="
                    surfaces[key]
                      ? 'border-emerald-500 bg-emerald-500/10 text-emerald-300'
                      : 'border-border/60 hover:bg-card/40 text-text/70'
                  "
                  @click="toggleSurface(key as keyof SurfaceState)"
                >
                  {{ label }} ({{ key }})
                </button>
              </div>
              <p v-if="selectedSurfaces.length === 0" class="text-sm text-text/60">
                Оберіть поверхні для лікування
              </p>
              <p v-else class="text-sm text-text/80">
                Обрані: {{ selectedSurfaces.join(', ') }}
              </p>
            </div>
          </div>

          <div class="p-6 border-t border-border flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="open = false">Скасувати</UIButton>
            <UIButton variant="primary" size="sm" @click="save">Зберегти</UIButton>
          </div>
        </div>
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
</style>

