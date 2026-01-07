<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { UIButton, UIAvatar, UIBadge } from '../ui'
import assistantApi from '../services/assistantApi'

const route = useRoute()
const router = useRouter()
const assistantId = computed(() => Number(route.params.id))

const loading = ref(true)
const error = ref<string | null>(null)
const assistant = ref<any | null>(null)
const saving = ref(false)
const saveMessage = ref<string | null>(null)
const isEditing = ref(false)

const form = ref({
  first_name: '',
  last_name: '',
  email: ''
})

const fullName = computed(() => {
  if (!assistant.value) return ''
  return (
    assistant.value.full_name ||
    assistant.value.name ||
    `${assistant.value.first_name || ''} ${assistant.value.last_name || ''}`.trim() ||
    assistant.value.email
  )
})

const avatarUrl = computed(() => assistant.value?.avatar_url || null)

const primaryClinic = computed(() => {
  return assistant.value?.clinics?.[0] || null
})

const statusVariant = computed(() => 'success')
const statusLabel = computed(() => 'Активний')

const loadAssistant = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await assistantApi.get(assistantId.value)
    assistant.value = data
    form.value = {
      first_name: data.first_name || '',
      last_name: data.last_name || '',
      email: data.email || ''
    }
  } catch (e: any) {
    console.error(e)
    error.value = e?.response?.data?.message || 'Не вдалося завантажити асистента'
  } finally {
    loading.value = false
  }
}

onMounted(loadAssistant)

const goBack = () => {
  router.push({ name: 'assistants' })
}

const save = async () => {
  if (!assistant.value) return
  saving.value = true
  saveMessage.value = null
  try {
    await assistantApi.update(assistantId.value, {
      first_name: form.value.first_name,
      last_name: form.value.last_name,
      email: form.value.email
    })
    await loadAssistant()
    isEditing.value = false
    saveMessage.value = 'Анкету оновлено'
  } catch (e: any) {
    console.error(e)
    saveMessage.value = e?.response?.data?.message || 'Не вдалося оновити анкету'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="text-sm text-text/70 hover:text-text flex items-center gap-1"
          @click="goBack"
        >
          ← До списку асистентів
        </button>
      </div>
    </header>

    <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
    <div v-else-if="error" class="text-sm text-rose-400">❌ {{ error }}</div>
    <div v-else-if="!assistant" class="text-sm text-text/70">Асистента не знайдено.</div>
    <div v-else class="grid gap-6 lg:grid-cols-[minmax(260px,320px),1fr]">
      <!-- Left column: profile card -->
      <section
        class="rounded-xl bg-card/80 border border-border/60 shadow-lg shadow-black/20 p-5 flex flex-col gap-4"
      >
        <div class="flex flex-col items-center gap-3">
          <div
            class="w-24 h-24 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center text-text font-bold shadow-md overflow-hidden"
          >
            <img
              v-if="avatarUrl"
              :src="avatarUrl"
              alt="assistant avatar"
              class="w-full h-full object-cover"
            />
            <span v-else>{{ fullName?.[0] || '?' }}</span>
          </div>
          <div class="text-center space-y-1">
            <h1 class="text-xl font-semibold text-text">
              {{ fullName }}
            </h1>
            <p class="text-xs text-text/60">Асистент</p>
            <UIBadge :variant="statusVariant" small>
              {{ statusLabel }}
            </UIBadge>
          </div>
        </div>

        <div class="space-y-3 text-sm">
          <div>
            <p class="text-xs text-text/60 uppercase mb-1">Клініка</p>
            <p class="text-text/90">
              {{ primaryClinic ? primaryClinic.name : '—' }}
            </p>
          </div>

          <div>
            <p class="text-xs text-text/60 uppercase mb-1">Email</p>
            <p class="text-text/90">{{ assistant.email }}</p>
          </div>
        </div>

        <p class="mt-2 text-xs text-text/60">
          Фото асистента змінюється у власному профілі користувача і автоматично оновлюється тут.
        </p>
      </section>

      <!-- Right column: form -->
      <section
        class="rounded-xl bg-card/80 border border-border/60 shadow-lg shadow-black/20 p-5 space-y-4"
      >
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-text">Анкета асистента</h2>
            <p class="text-xs text-text/60">Базові дані асистента для адміністрування.</p>
          </div>
          <UIButton variant="secondary" size="sm" @click="isEditing = !isEditing">
            {{ isEditing ? 'Скасувати' : 'Редагувати' }}
          </UIButton>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Ім'я</label>
            <input
              v-model="form.first_name"
              type="text"
              :disabled="!isEditing"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-60"
            />
          </div>
          <div>
            <label class="block text-xs uppercase text-text/70 mb-1">Прізвище</label>
            <input
              v-model="form.last_name"
              type="text"
              :disabled="!isEditing"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-60"
            />
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs uppercase text-text/70 mb-1">Email</label>
            <input
              v-model="form.email"
              type="email"
              :disabled="!isEditing"
              class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-60"
            />
          </div>
        </div>

        <div v-if="saveMessage" class="text-xs text-emerald-300">
          {{ saveMessage }}
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <UIButton v-if="isEditing" variant="secondary" size="sm" :loading="saving" @click="save">
            Зберегти
          </UIButton>
        </div>
      </section>
    </div>
  </div>
</template>
