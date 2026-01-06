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
const roles = ref<Array<{ id: number; name: string }>>([])
const loadingClinics = ref(false)
const loadingRoles = ref(false)
const selectedClinicId = ref<number | string | null>(null)
const selectedRoleName = ref<string>('')
const saving = ref(false)

const displayName = computed(() => {
  if (props.user?.first_name || props.user?.last_name) {
    return `${props.user.first_name || ''} ${props.user.last_name || ''}`.trim()
  }
  return props.user?.name || props.user?.email || ''
})

const email = computed(() => props.user?.email || '')
const currentRole = computed(() => {
  if (props.user?.roles && props.user.roles.length > 0) {
    return props.user.roles[0].name
  }
  return null
})

const isSuperAdmin = computed(() => authUser.value?.global_role === 'super_admin')
const needsClinic = computed(() => {
  return selectedRoleName.value === 'doctor' || selectedRoleName.value === 'assistant'
})

const loadClinics = async () => {
  loadingClinics.value = true
  try {
    if (isSuperAdmin.value) {
      const { data } = await clinicApi.list()
      clinics.value = Array.isArray(data?.data) ? data.data : Array.isArray(data) ? data : []
    } else {
      const { data } = await clinicApi.listMine()
      clinics.value = (data.clinics || []).map((c: any) => ({
        id: c.clinic_id,
        name: c.clinic_name
      }))
    }
    
    // Set default clinic for non-super-admin
    if (!isSuperAdmin.value && clinics.value.length > 0 && !selectedClinicId.value) {
      selectedClinicId.value = clinics.value[0].id
    }
  } catch (e) {
    console.error('Failed to load clinics', e)
    showToast('Не вдалося завантажити клініки', 'error')
  } finally {
    loadingClinics.value = false
  }
}

const loadRoles = async () => {
  loadingRoles.value = true
  try {
    const { data } = await roleApi.listAllRoles()
    roles.value = data.roles || []
    
    // Set current role if user has one
    if (currentRole.value) {
      selectedRoleName.value = currentRole.value
    }
  } catch (e) {
    console.error('Failed to load roles', e)
    showToast('Не вдалося завантажити ролі', 'error')
  } finally {
    loadingRoles.value = false
  }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      selectedRoleName.value = currentRole.value || ''
      selectedClinicId.value = null
      loadClinics()
      loadRoles()
    }
  },
  { immediate: true }
)

const save = async () => {
  if (!selectedRoleName.value) {
    showToast('Оберіть роль', 'error')
    return
  }

  if (needsClinic.value && !selectedClinicId.value) {
    showToast('Оберіть клініку для ролей doctor або assistant', 'error')
    return
  }

  saving.value = true
  try {
    await roleApi.assignRole(
      props.user.id,
      selectedRoleName.value,
      needsClinic.value ? selectedClinicId.value as number : null
    )
    
    showToast('Роль оновлено', 'success')
    emit('saved')
    open.value = false
  } catch (e: any) {
    const message = e?.response?.data?.message || 'Не вдалося оновити роль'
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
        <div class="w-full max-w-md rounded-2xl bg-card text-text shadow-2xl border border-border p-6 space-y-6">
          <div>
            <h2 class="text-xl font-semibold mb-2">Редагувати співробітника</h2>
            <p class="text-sm text-text/70">Призначте роль та клініку</p>
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
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                Email
              </label>
              <div class="rounded-lg bg-bg/50 border border-border/80 px-3 py-2 text-sm opacity-70">
                {{ email }}
              </div>
            </div>

            <div>
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                Роль <span class="text-red-400">*</span>
              </label>
              <select
                v-model="selectedRoleName"
                :disabled="loadingRoles"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-50"
              >
                <option value="">-- Оберіть роль --</option>
                <option v-for="role in roles" :key="role.id" :value="role.name">
                  {{ role.name }}
                </option>
              </select>
            </div>

            <div v-if="needsClinic">
              <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                Клініка <span class="text-red-400">*</span>
              </label>
              <select
                v-model="selectedClinicId"
                :disabled="loadingClinics || !isSuperAdmin"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text disabled:opacity-50"
              >
                <option :value="null">-- Оберіть клініку --</option>
                <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">
                  {{ clinic.name }}
                </option>
              </select>
              <p v-if="!isSuperAdmin" class="text-xs text-text/60 mt-1">
                Ваша клініка вибрана автоматично
              </p>
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

