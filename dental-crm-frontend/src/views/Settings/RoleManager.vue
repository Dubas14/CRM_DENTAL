<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useToast } from '../../composables/useToast'
import { UIButton, UIBadge } from '../../ui'
import roleApi from '../../services/roleApi'

const { showToast } = useToast()

interface Permission {
  id: number
  name: string
}

interface PermissionGroup {
  module: string
  permissions: Permission[]
}

interface Role {
  id: number
  name: string
  permissions: string[]
}

const roles = ref<Role[]>([])
const permissions = ref<PermissionGroup[]>([])
const loading = ref(false)
const saving = ref(false)
const showRoleForm = ref(false)
const editingRole = ref<Role | null>(null)
const roleName = ref('')
const selectedPermissions = ref<string[]>([])

const moduleLabels: Record<string, string> = {
  appointment: 'Календар (системне)',
  invoice: 'Фінанси (системне)',
  payment: 'Фінанси (системне)',
  inventory: 'Склад',
  medical: 'Медичні записи',
  patient: 'Пацієнти',
  user: 'Користувачі',
  role: 'Ролі (системне)',
  clinic: 'Клініки',
  procedure: 'Процедури',
  specialization: 'Спеціалізації',
  other: 'Інше'
}

const permissionLabels: Record<string, string> = {
  // Calendar
  'appointment.view': 'Перегляд календаря',
  'appointment.create': 'Створення записів',
  'appointment.update': 'Редагування записів',
  'appointment.delete': 'Видалення записів',
  'appointment.cancel': 'Скасування записів',
  'calendar.view': 'Перегляд календаря',
  'calendar.manage': 'Керування календарем',

  // Finance
  'invoice.view': 'Перегляд рахунків',
  'invoice.create': 'Створення рахунків',
  'invoice.update': 'Редагування рахунків',
  'invoice.delete': 'Видалення рахунків',
  'payment.collect': 'Прийом оплат',
  'payment.view': 'Перегляд оплат',

  // Inventory
  'inventory.view': 'Перегляд складу',
  'inventory.manage': 'Керування складом',
  'inventory.transaction.create': 'Створення рухів складу',
  'inventory.transaction.view': 'Перегляд рухів складу',

  // Medical
  'medical.view': 'Перегляд медичних записів',
  'medical.edit': 'Редагування медичних записів',
  'medical.record.create': 'Створення медичних записів',
  'medical.record.update': 'Оновлення медичних записів',

  // Patients
  'patient.view': 'Перегляд пацієнтів',
  'patient.create': 'Створення пацієнтів',
  'patient.update': 'Редагування пацієнтів',
  'patient.delete': 'Видалення пацієнтів',

  // Users / Roles / Clinics
  'user.view': 'Перегляд користувачів',
  'user.create': 'Створення користувачів',
  'user.update': 'Редагування користувачів',
  'user.delete': 'Видалення користувачів',
  'role.manage': 'Керування ролями',
  'clinic.view': 'Перегляд клінік',
  'clinic.create': 'Створення клінік',
  'clinic.update': 'Редагування клінік',
  'clinic.delete': 'Видалення клінік',

  // Catalogs
  'procedure.view': 'Перегляд процедур',
  'procedure.manage': 'Керування процедурами',
  'specialization.view': 'Перегляд спеціалізацій',
  'specialization.manage': 'Керування спеціалізаціями',

  // Fallbacks if present
  'inventory.transaction': 'Рухи складу'
}

const formatPermission = (name: string) => {
  return permissionLabels[name] || name
}

const loadRoles = async () => {
  loading.value = true
  try {
    const { data } = await roleApi.listAllRoles()
    roles.value = data.roles || []
    permissions.value = data.permissions || []
  } catch (err: any) {
    console.error(err)
    showToast('Не вдалося завантажити ролі', 'error')
  } finally {
    loading.value = false
  }
}

const openCreateForm = () => {
  editingRole.value = null
  roleName.value = ''
  selectedPermissions.value = []
  showRoleForm.value = true
}

const openEditForm = (role: Role) => {
  editingRole.value = role
  roleName.value = role.name
  selectedPermissions.value = [...role.permissions]
  showRoleForm.value = true
}

const togglePermission = (permissionName: string) => {
  const index = selectedPermissions.value.indexOf(permissionName)
  if (index > -1) {
    selectedPermissions.value.splice(index, 1)
  } else {
    selectedPermissions.value.push(permissionName)
  }
}

const saveRole = async () => {
  if (!roleName.value.trim()) {
    showToast('Введіть назву ролі', 'error')
    return
  }

  saving.value = true
  try {
    if (editingRole.value) {
      await roleApi.updateRole(editingRole.value.id, roleName.value, selectedPermissions.value)
      showToast('Роль оновлено', 'success')
    } else {
      await roleApi.createRole(roleName.value, selectedPermissions.value)
      showToast('Роль створено', 'success')
    }
    showRoleForm.value = false
    await loadRoles()
  } catch (err: any) {
    const message = err?.response?.data?.message || 'Не вдалося зберегти роль'
    showToast(message, 'error')
  } finally {
    saving.value = false
  }
}

const cancelForm = () => {
  showRoleForm.value = false
  editingRole.value = null
  roleName.value = ''
  selectedPermissions.value = []
}

onMounted(loadRoles)
</script>

<template>
  <div class="space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Налаштування Ролей</h1>
        <p class="text-sm text-text/70">Створюйте та редагуйте ролі з правами доступу</p>
      </div>
      <UIButton variant="secondary" size="sm" @click="openCreateForm"> + Створити роль </UIButton>
    </header>

    <section
      class="rounded-xl bg-card/40 shadow-sm shadow-black/10 dark:shadow-black/40 p-4 space-y-4"
    >
      <div v-if="loading" class="text-sm text-text/70">Завантаження...</div>
      <div v-else-if="roles.length === 0" class="text-sm text-text/70">Ролей поки немає</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-text/70 text-xs uppercase">
            <tr>
              <th class="text-left py-2 px-3">Назва ролі</th>
              <th class="text-left py-2 px-3">Права</th>
              <th class="text-left py-2 px-3">Дії</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in roles" :key="role.id" class="border-t border-border">
              <td class="py-2 px-3 text-text/90 font-medium">{{ role.name }}</td>
              <td class="py-2 px-3">
                <div class="flex flex-wrap gap-1">
                  <UIBadge
                    v-for="perm in role.permissions.slice(0, 5)"
                    :key="perm"
                    variant="info"
                    small
                  >
                    {{ formatPermission(perm) }}
                  </UIBadge>
                  <span v-if="role.permissions.length > 5" class="text-xs text-text/60">
                    +{{ role.permissions.length - 5 }} ще
                  </span>
                  <span v-else-if="role.permissions.length === 0" class="text-xs text-text/60">
                    Немає прав
                  </span>
                </div>
              </td>
              <td class="py-2 px-3 text-right">
                <UIButton variant="ghost" size="sm" @click="openEditForm(role)">
                  Редагувати
                </UIButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Role Form Modal -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="showRoleForm"
          class="fixed inset-0 z-[2000] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4"
          @click.self="cancelForm"
        >
          <div
            class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-2xl bg-card text-text shadow-2xl border border-border p-6 space-y-6"
          >
            <div>
              <h2 class="text-xl font-semibold mb-2">
                {{ editingRole ? 'Редагувати роль' : 'Створити роль' }}
              </h2>
              <p class="text-sm text-text/70">Налаштуйте назву та права доступу</p>
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-xs uppercase tracking-wide text-text/70 mb-1">
                  Назва ролі <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="roleName"
                  type="text"
                  placeholder="Наприклад: Касир, Старший лікар"
                  class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm text-text"
                />
              </div>

              <div>
                <label class="block text-xs uppercase tracking-wide text-text/70 mb-3">
                  Права доступу
                </label>
                <div class="space-y-6">
                  <div
                    v-for="group in permissions"
                    :key="group.module"
                    class="rounded-lg border border-border/60 p-4 bg-bg/40"
                  >
                    <h3 class="text-sm font-semibold text-text/90 mb-3">
                      {{ moduleLabels[group.module] || group.module }}
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                      <label
                        v-for="perm in group.permissions"
                        :key="perm.id"
                        class="flex items-center gap-2 text-sm text-text/80 cursor-pointer hover:text-text"
                      >
                        <input
                          type="checkbox"
                          :checked="selectedPermissions.includes(perm.name)"
                          @change="togglePermission(perm.name)"
                          class="rounded border-border/80 bg-bg text-emerald-500 focus:ring-emerald-500"
                        />
                        <span>{{ formatPermission(perm.name) }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-border">
              <UIButton variant="ghost" size="sm" @click="cancelForm">Скасувати</UIButton>
              <UIButton variant="secondary" size="sm" :loading="saving" @click="saveRole">
                {{ editingRole ? 'Оновити' : 'Створити' }}
              </UIButton>
            </div>
          </div>
        </div>
      </transition>
    </Teleport>
  </div>
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
