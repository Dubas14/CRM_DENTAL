<script setup lang="ts">
import { ref, onMounted } from 'vue'
import specializationApi from '../services/specializationApi'
import { UIButton } from '../ui'

const items = ref<any[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const creating = ref(false)
const createName = ref('')
const createError = ref<string | null>(null)
const editingId = ref<number | null>(null)
const editingName = ref('')
const savingEdit = ref(false)
const deletingId = ref<number | null>(null)

const load = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await specializationApi.list()
    items.value = Array.isArray(data) ? data : (data?.data ?? [])
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося завантажити спеціалізації'
  } finally {
    loading.value = false
  }
}

onMounted(load)

const createSpec = async () => {
  if (!createName.value.trim()) {
    createError.value = 'Введіть назву спеціалізації'
    return
  }
  creating.value = true
  createError.value = null
  try {
    await specializationApi.create({ name: createName.value.trim() })
    createName.value = ''
    await load()
  } catch (e: any) {
    console.error(e)
    createError.value = e?.response?.data?.message || 'Не вдалося створити спеціалізацію'
  } finally {
    creating.value = false
  }
}

const toggleActive = async (spec: any) => {
  try {
    await specializationApi.update(spec.id, { is_active: !spec.is_active })
    await load()
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося оновити спеціалізацію'
  }
}

const startEdit = (spec: any) => {
  editingId.value = spec.id
  editingName.value = spec.name
}

const cancelEdit = () => {
  editingId.value = null
  editingName.value = ''
}

const saveEdit = async () => {
  if (!editingId.value) return
  if (!editingName.value.trim()) {
    error.value = 'Назва не може бути порожньою'
    return
  }
  savingEdit.value = true
  error.value = null
  try {
    await specializationApi.update(editingId.value, { name: editingName.value.trim() })
    cancelEdit()
    await load()
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося зберегти спеціалізацію'
  } finally {
    savingEdit.value = false
  }
}

const removeSpec = async (spec: any) => {
  if (!window.confirm(`Видалити спеціалізацію "${spec.name}"?`)) return
  deletingId.value = spec.id
  error.value = null
  try {
    await specializationApi.remove(spec.id)
    if (editingId.value === spec.id) {
      cancelEdit()
    }
    await load()
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося видалити спеціалізацію'
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-text">Спеціалізації</h1>
        <p class="text-sm text-text/70">Довідник спеціалізацій лікарів.</p>
      </div>
    </header>

    <section
      class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <div class="grid md:grid-cols-[2fr,1fr] gap-4 items-end">
        <div>
          <label class="block text-xs uppercase text-text/60 mb-1">Нова спеціалізація</label>
          <input
            v-model="createName"
            type="text"
            placeholder="Наприклад, Стоматолог-ортопед"
            class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
          />
        </div>
        <div class="flex items-center gap-3">
          <span v-if="createError" class="text-xs text-rose-400">{{ createError }}</span>
          <UIButton
            variant="secondary"
            size="sm"
            class="ml-auto"
            :loading="creating"
            @click="createSpec"
          >
            Додати
          </UIButton>
        </div>
      </div>
    </section>

    <section class="rounded-xl bg-card/60 shadow-sm shadow-black/10 dark:shadow-black/40 p-4">
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="error" class="text-sm text-rose-400">{{ error }}</div>
      <div v-else-if="!items.length" class="text-sm text-text/70">Немає спеціалізацій.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-card/80 border-b border-border">
            <tr class="text-left text-text/70">
              <th class="px-4 py-2">Назва</th>
              <th class="px-4 py-2">Статус</th>
              <th class="px-4 py-2 text-right">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="spec in items" :key="spec.id" class="border-t border-border/60">
              <td class="px-4 py-3 text-text/90">
                <div v-if="editingId === spec.id" class="flex items-center gap-2">
                  <input
                    v-model="editingName"
                    type="text"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                  />
                  <UIButton variant="secondary" size="sm" :loading="savingEdit" @click="saveEdit"
                    >Зберегти</UIButton
                  >
                  <UIButton variant="ghost" size="sm" @click="cancelEdit">Скасувати</UIButton>
                </div>
                <div v-else class="flex items-center gap-2">
                  <span>{{ spec.name }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                  :class="
                    spec.is_active
                      ? 'bg-emerald-500/20 text-emerald-300'
                      : 'bg-border/40 text-text/60'
                  "
                >
                  {{ spec.is_active ? 'Активна' : 'Неактивна' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2 text-sm">
                  <UIButton
                    variant="ghost"
                    size="sm"
                    class="!text-emerald-400 hover:!text-emerald-300 !px-0"
                    @click="startEdit(spec)"
                  >
                    Редагувати
                  </UIButton>
                  <UIButton
                    variant="ghost"
                    size="sm"
                    class="!text-rose-400 hover:!text-rose-300 !px-0"
                    :loading="deletingId === spec.id"
                    @click="removeSpec(spec)"
                  >
                    Видалити
                  </UIButton>
                  <UIButton variant="ghost" size="sm" @click="toggleActive(spec)">
                    {{ spec.is_active ? 'Деактивувати' : 'Активувати' }}
                  </UIButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
