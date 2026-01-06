<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '../services/apiClient'
import ToothSurfaceSelector from './dental/ToothSurfaceSelector.vue'
import { UIBadge } from '../ui'

const props = defineProps({
  patientId: { type: Number, required: true }
})

const teethMap = ref<Record<number, { status: string; surfaces?: string[] | null }>>({}) // Зберігаємо стан зубів та поверхні
const loading = ref(false)
const selectedTooth = ref<number | null>(null) // Який зуб зараз редагуємо
const showSurfaceSelector = ref(false)

// Генерація номерів зубів (Дорослі)
// Верхня щелепа: Q1 (18-11), Q2 (21-28)
// Нижня щелепа: Q4 (48-41), Q3 (31-38)
const quadrants = {
  q1: [18, 17, 16, 15, 14, 13, 12, 11],
  q2: [21, 22, 23, 24, 25, 26, 27, 28],
  q4: [48, 47, 46, 45, 44, 43, 42, 41],
  q3: [31, 32, 33, 34, 35, 36, 37, 38]
}

// Доступні статуси
const statuses = [
  { id: 'healthy', label: 'Здоровий', color: 'bg-card border-border', fill: '#ffffff' },
  {
    id: 'caries',
    label: 'Карієс',
    color: 'bg-amber-100 border-amber-500 text-amber-700',
    fill: '#fcd34d'
  },
  {
    id: 'pulpitis',
    label: 'Пульпіт',
    color: 'bg-red-100 border-red-500 text-red-700',
    fill: '#ef4444'
  },
  {
    id: 'filled',
    label: 'Пломба',
    color: 'bg-blue-100 border-blue-500 text-blue-700',
    fill: '#3b82f6'
  },
  {
    id: 'missing',
    label: 'Відсутній',
    color: 'bg-card/80 border-border text-text/70',
    fill: '#374151'
  },
  {
    id: 'crown',
    label: 'Коронка',
    color: 'bg-yellow-100 border-yellow-600 text-yellow-800',
    fill: '#eab308'
  }
]

interface DentalMapItem {
  tooth_number: number
  status: string
  surfaces?: string[] | null
}

// Завантаження даних
const loadMap = async () => {
  loading.value = true
  try {
    const { data } = await apiClient.get(`/patients/${props.patientId}/dental-map`)
    // Перетворюємо масив об'єктів у зручний об'єкт { "18": { status, surfaces } }
    teethMap.value = (data as DentalMapItem[]).reduce(
      (acc: Record<number, { status: string; surfaces?: string[] | null }>, item: DentalMapItem) => {
        acc[item.tooth_number] = {
          status: item.status,
          surfaces: item.surfaces || null
        }
        return acc
      },
      {}
    )
  } catch (e) {
    console.error('Помилка завантаження карти:', e)
  } finally {
    loading.value = false
  }
}

// Збереження статусу
const setStatus = async (statusId: string) => {
  if (!selectedTooth.value) return

  const toothNum = selectedTooth.value
  const currentData = teethMap.value[toothNum] || { status: 'healthy', surfaces: null }
  // Оптимістичне оновлення інтерфейсу
  teethMap.value[toothNum] = { ...currentData, status: statusId }
  selectedTooth.value = null // Закрити меню

  try {
    await apiClient.post(`/patients/${props.patientId}/dental-map`, {
      tooth_number: toothNum,
      status: statusId,
      surfaces: currentData.surfaces
    })
  } catch {
    alert('Не вдалося зберегти статус зуба')
    loadMap() // Відкат змін
  }
}

// Збереження поверхонь
const saveSurfaces = async (surfaces: string[]) => {
  if (!selectedTooth.value) return

  const toothNum = selectedTooth.value
  const currentData = teethMap.value[toothNum] || { status: 'healthy', surfaces: null }
  // Оптимістичне оновлення інтерфейсу
  teethMap.value[toothNum] = { ...currentData, surfaces }
  showSurfaceSelector.value = false
  selectedTooth.value = null

  try {
    await apiClient.post(`/patients/${props.patientId}/dental-map`, {
      tooth_number: toothNum,
      status: currentData.status,
      surfaces: surfaces.length > 0 ? surfaces : null
    })
  } catch {
    alert('Не вдалося зберегти поверхні зуба')
    loadMap() // Відкат змін
  }
}

const openSurfaceSelector = () => {
  showSurfaceSelector.value = true
}

// Отримати колір для SVG
const getFillColor = (toothNum: number) => {
  const data = teethMap.value[toothNum]
  const statusId = data?.status || 'healthy'
  const status = statuses.find((s) => s.id === statusId)
  return status ? status.fill : '#ffffff'
}

// Отримати поверхні для зуба
const getSurfaces = (toothNum: number): string[] | null => {
  return teethMap.value[toothNum]?.surfaces || null
}

// Отримати бейдж поверхонь
const getSurfacesBadge = (toothNum: number): string | null => {
  const surfaces = getSurfaces(toothNum)
  return surfaces && surfaces.length > 0 ? surfaces.join('') : null
}

onMounted(loadMap)
</script>

<template>
  <div class="bg-card p-6 rounded-xl shadow-sm shadow-black/10 dark:shadow-black/40">
    <h3 class="text-lg font-semibold text-text/70 mb-4">Зубна формула</h3>

    <div v-if="loading" class="text-center py-10 text-text/70">Завантаження карти...</div>

    <div v-else class="relative">
      <div class="flex flex-col gap-8 items-center overflow-x-auto pb-4">
        <div class="flex gap-4">
          <div class="flex gap-1">
            <div
              v-for="t in quadrants.q1"
              :key="t"
              @click="selectedTooth = t"
              class="cursor-pointer flex flex-col items-center group"
            >
              <span class="text-xs text-text/70 mb-1">{{ t }}</span>
              <div class="relative">
                <svg
                  width="40"
                  height="50"
                  viewBox="0 0 40 50"
                  class="text-border transition-transform group-hover:scale-110"
                >
                  <path
                    d="M5,15 Q5,0 20,0 Q35,0 35,15 L35,40 Q35,50 20,50 Q5,50 5,40 Z"
                    :fill="getFillColor(t)"
                    stroke="currentColor"
                    stroke-width="2"
                  />
                  <path
                    v-if="teethMap[t]?.status === 'missing'"
                    d="M10,10 L30,40 M30,10 L10,40"
                    stroke="currentColor"
                    stroke-width="3"
                  />
                </svg>
                <UIBadge
                  v-if="getSurfacesBadge(t)"
                  variant="info"
                  small
                  class="absolute -top-1 -right-1 text-[8px] px-1 py-0"
                >
                  {{ getSurfacesBadge(t) }}
                </UIBadge>
              </div>
            </div>
          </div>
          <div class="w-px bg-border mx-2"></div>
          <div class="flex gap-1">
            <div
              v-for="t in quadrants.q2"
              :key="t"
              @click="selectedTooth = t"
              class="cursor-pointer flex flex-col items-center group"
            >
              <span class="text-xs text-text/70 mb-1">{{ t }}</span>
              <div class="relative">
                <svg
                  width="40"
                  height="50"
                  viewBox="0 0 40 50"
                  class="text-border transition-transform group-hover:scale-110"
                >
                  <path
                    d="M5,15 Q5,0 20,0 Q35,0 35,15 L35,40 Q35,50 20,50 Q5,50 5,40 Z"
                    :fill="getFillColor(t)"
                    stroke="currentColor"
                    stroke-width="2"
                  />
                  <path
                    v-if="teethMap[t]?.status === 'missing'"
                    d="M10,10 L30,40 M30,10 L10,40"
                    stroke="currentColor"
                    stroke-width="3"
                  />
                </svg>
                <UIBadge
                  v-if="getSurfacesBadge(t)"
                  variant="info"
                  small
                  class="absolute -top-1 -right-1 text-[8px] px-1 py-0"
                >
                  {{ getSurfacesBadge(t) }}
                </UIBadge>
              </div>
            </div>
          </div>
        </div>

        <div class="flex gap-4">
          <div class="flex gap-1">
            <div
              v-for="t in quadrants.q4"
              :key="t"
              @click="selectedTooth = t"
              class="cursor-pointer flex flex-col items-center group"
            >
              <div class="relative">
                <svg
                  width="40"
                  height="50"
                  viewBox="0 0 40 50"
                  class="text-border transition-transform group-hover:scale-110"
                >
                  <path
                    d="M5,35 Q5,50 20,50 Q35,50 35,35 L35,10 Q35,0 20,0 Q5,0 5,10 Z"
                    :fill="getFillColor(t)"
                    stroke="currentColor"
                    stroke-width="2"
                  />
                  <path
                    v-if="teethMap[t]?.status === 'missing'"
                    d="M10,10 L30,40 M30,10 L10,40"
                    stroke="currentColor"
                    stroke-width="3"
                  />
                </svg>
                <UIBadge
                  v-if="getSurfacesBadge(t)"
                  variant="info"
                  small
                  class="absolute -top-1 -right-1 text-[8px] px-1 py-0"
                >
                  {{ getSurfacesBadge(t) }}
                </UIBadge>
              </div>
              <span class="text-xs text-text/70 mt-1">{{ t }}</span>
            </div>
          </div>
          <div class="w-px bg-border mx-2"></div>
          <div class="flex gap-1">
            <div
              v-for="t in quadrants.q3"
              :key="t"
              @click="selectedTooth = t"
              class="cursor-pointer flex flex-col items-center group"
            >
              <div class="relative">
                <svg
                  width="40"
                  height="50"
                  viewBox="0 0 40 50"
                  class="text-border transition-transform group-hover:scale-110"
                >
                  <path
                    d="M5,35 Q5,50 20,50 Q35,50 35,35 L35,10 Q35,0 20,0 Q5,0 5,10 Z"
                    :fill="getFillColor(t)"
                    stroke="currentColor"
                    stroke-width="2"
                  />
                  <path
                    v-if="teethMap[t]?.status === 'missing'"
                    d="M10,10 L30,40 M30,10 L10,40"
                    stroke="currentColor"
                    stroke-width="3"
                  />
                </svg>
                <UIBadge
                  v-if="getSurfacesBadge(t)"
                  variant="info"
                  small
                  class="absolute -top-1 -right-1 text-[8px] px-1 py-0"
                >
                  {{ getSurfacesBadge(t) }}
                </UIBadge>
              </div>
              <span class="text-xs text-text/70 mt-1">{{ t }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-8 flex flex-wrap gap-3 justify-center border-t border-border/60 pt-4">
        <div v-for="s in statuses" :key="s.id" class="flex items-center gap-2">
          <div
            class="w-4 h-4 rounded border"
            :style="{
              backgroundColor: s.fill,
              borderColor: s.id === 'healthy' ? '#cbd5e1' : s.fill
            }"
          ></div>
          <span class="text-sm text-text/60">{{ s.label }}</span>
        </div>
      </div>

      <div
        v-if="selectedTooth && !showSurfaceSelector"
        class="fixed inset-0 bg-text/20 dark:bg-bg/50 flex items-center justify-center z-50"
        @click.self="selectedTooth = null"
      >
        <div class="bg-card rounded-xl shadow-xl p-6 w-80 max-w-full">
          <h3 class="text-lg font-bold text-text/80 mb-4">Зуб № {{ selectedTooth }}</h3>
          <p class="text-sm text-text/60 mb-4">Оберіть поточний стан:</p>

          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="s in statuses"
              :key="s.id"
              @click="setStatus(s.id)"
              class="px-3 py-2 rounded border text-sm font-medium transition-colors"
              :class="s.color"
            >
              {{ s.label }}
            </button>
          </div>

          <button
            @click="openSurfaceSelector"
            class="mt-4 w-full py-2 bg-emerald-500/10 text-emerald-300 rounded border border-emerald-500/30 text-sm font-medium hover:bg-emerald-500/20 transition"
          >
            Оберіть поверхні
          </button>

          <button
            @click="selectedTooth = null"
            class="mt-2 w-full py-2 text-text/70 text-sm hover:text-text/60"
          >
            Скасувати
          </button>
        </div>
      </div>

      <ToothSurfaceSelector
        v-model="showSurfaceSelector"
        :tooth-number="selectedTooth || 0"
        :initial-surfaces="selectedTooth ? getSurfaces(selectedTooth) : null"
        @save="saveSurfaces"
      />
    </div>
  </div>
</template>
