<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useToast } from '../../composables/useToast'
import { UIButton } from '../../ui'
import roleApi from '../../services/roleApi'
import clinicApi from '../../services/clinicApi'
import { useAuth } from '../../composables/useAuth'

const props = defineProps<{
  modelValue: boolean
  user: any
  selectedRoles: string[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved'): void
}>()

const { showToast } = useToast()
const { user: authUser } = useAuth()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const clinics = ref<Array<{ id: number | string; name: string }>>([])
const loadingClinics = ref(false)
const selectedClinicId = ref<number | string | null>(null)
const fullName = ref('')
const phone = ref('')
const saving = ref(false)

const displayName = computed(() => {
  if (props.user?.first_name || props.user?.last_name) {
    return `${props.user.first_name || ''} ${props.user.last_name || ''}`.trim()
  }
  return props.user?.name || props.user?.email || ''
})

const email = computed(() => props.user?.email || '')

const needsClinic = computed(() => {
  return props.selectedRoles.some((r) => r === 'doctor' || r === 'assistant')
})

// Removed unused computed

const loadClinics = async () => {
  loadingClinics.value = true
  try {
    if (authUser.value?.global_role === 'super_admin') {
      const { data } = await clinicApi.list()
      clinics.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    } else {
      const { data } = await clinicApi.listMine()
      clinics.value = (data.clinics || []).map((c: any) => ({
        id: c.clinic_id,
        name: c.clinic_name
      }))
    }

    if (clinics.value.length > 0 && !selectedClinicId.value) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (e) {
    console.error('Failed to load clinics', e)
    showToast('Не вдалося завантажити клініки', 'error')
  } finally {
    loadingClinics.value = false
  }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      // Initialize form when modal opens
      fullName.value = displayName.value
      phone.value = props.user?.doctor?.phone || ''
      loadClinics()
    }
  },
  { immediate: true }
)

const save = async () => {
  if (needsClinic.value && !selectedClinicId.value) {
    showToast('Оберіть клініку для ролей doctor або assistant', 'error')
    return
  }

  if (props.selectedRoles.length === 0) {
    showToast('Оберіть хоча б одну роль', 'error')
    return
  }

  saving.value = true
  try {
    await roleApi.updateUserRoles(
      props.user.id,
      props.selectedRoles,
      needsClinic.value ? selectedClinicId.value : null
    )

    showToast('Ролі оновлено', 'success')
    emit('saved')
    open.value = false
  } catch (e: any) {
    const message = e?.response?.data?.message || 'Не вдалося оновити ролі'
    showToast(message, 'error')
  } finally {
    saving.value = false
  }
}

const cancel = () => {
  open.value = false
}
</script>

<template>
  <Teleport to="body">
    <transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
        @click.self="cancel"
      >
        <div
          class="w-full max-w-2xl rounded-2xl bg-card text-text shadow-2xl border border-border p-6 space-y-6"
        >
          <div>
            <h2 class="text-xl font-semibold mb-2">Призначення ролі</h2>
            <p class="text-sm text-text/70">Налаштуйте роль та клініку для користувача</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                Користувач
              </label>
              <div class="rounded-lg bg-bg/50 border border-border/80 px-3 py-2 text-sm">
                {{ displayName }}
              </div>
            </div>

            <div>
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1"> Email </label>
              <div class="rounded-lg bg-bg/50 border border-border/80 px-3 py-2 text-sm opacity-70">
                {{ email }}
              </div>
            </div>

            <div>
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1"> Ролі </label>
              <div class="rounded-lg bg-bg/50 border border-border/80 px-3 py-2 text-sm space-y-1">
                <div v-for="role in selectedRoles" :key="role" class="flex items-center gap-2">
                  <span
                    class="inline-flex items-center px-2 py-1 rounded bg-emerald-500/20 text-emerald-300 text-xs"
                  >
                    {{ role }}
                  </span>
                </div>
                <p v-if="selectedRoles.length === 0" class="text-text/50">Ролі не обрані</p>
              </div>
            </div>

            <div v-if="needsClinic">
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                Клініка <span class="text-red-400">*</span>
              </label>
              <select
                v-model="selectedClinicId"
                :disabled="loadingClinics"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-50"
              >
                <option :value="null">-- Оберіть клініку --</option>
                <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
                  {{ clinic.name }}
                </option>
              </select>
              <p class="text-xs text-text/60 mt-1">
                Для ролей "doctor" та "assistant" обов'язково вкажіть клініку
              </p>
            </div>

            <div v-if="props.selectedRoles.includes('doctor')">
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1"> ПІБ </label>
              <input
                v-model="fullName"
                type="text"
                placeholder="Повне ім'я"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              />
            </div>

            <div
              v-if="
                props.selectedRoles.includes('doctor') || props.selectedRoles.includes('assistant')
              "
            >
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                Телефон
              </label>
              <input
                v-model="phone"
                type="text"
                placeholder="+380..."
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
              />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-border">
            <UIButton variant="ghost" size="sm" @click="cancel">Скасувати</UIButton>
            <UIButton variant="secondary" size="sm" :loading="saving" @click="save">
              Зберегти
            </UIButton>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
