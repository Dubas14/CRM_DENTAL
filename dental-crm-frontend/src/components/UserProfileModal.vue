<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useToast } from '../composables/useToast'
import { UIButton, UIAvatar } from '../ui'
import { doctorsApi as doctorApi } from '../features/doctors/api'
import userApi from '../services/userApi'

const props = defineProps<{
  modelValue: boolean
  user: any
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'updated'): void
}>()

const { showToast } = useToast()

const open = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
})

const doctorId = computed(() => props.user?.doctor?.id || null)
const avatarUrl = computed(() => props.user?.avatar_url || props.user?.doctor?.avatar_url || null)

const phone = ref('')
const email = computed(() => props.user?.email || '')
const avatarFile = ref<File | null>(null)
const avatarPreview = ref<string | null>(null)
const removeAvatar = ref(false)
const avatarInputRef = ref<HTMLInputElement | null>(null)

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirm = ref('')
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)
const changePassword = ref(false)

const saving = ref(false)

watch(
  () => props.user,
  () => {
    phone.value = props.user?.doctor?.phone || ''
    avatarPreview.value = null
    avatarFile.value = null
    removeAvatar.value = false
    currentPassword.value = ''
    newPassword.value = ''
    newPasswordConfirm.value = ''
    changePassword.value = false
  },
  { immediate: true }
)

const onAvatarChange = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  // Валідація розміру файлу (10MB)
  const maxSize = 10 * 1024 * 1024 // 10MB в байтах
  if (file.size > maxSize) {
    showToast('Розмір файлу не повинен перевищувати 10MB', 'error')
    // Очистити input
    if (avatarInputRef.value) {
      avatarInputRef.value.value = ''
    }
    return
  }

  // Валідація типу файлу
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
  if (!allowedTypes.includes(file.type)) {
    showToast('Файл повинен бути зображенням (JPEG, PNG, GIF або WebP)', 'error')
    if (avatarInputRef.value) {
      avatarInputRef.value.value = ''
    }
    return
  }

  avatarFile.value = file
  avatarPreview.value = URL.createObjectURL(file)
  removeAvatar.value = false
}

const onRemoveAvatar = () => {
  avatarFile.value = null
  avatarPreview.value = null
  removeAvatar.value = true
}

const validatePasswords = () => {
  if (!changePassword.value) {
    // Пароль не змінюємо
    return { valid: true, shouldUpdate: false }
  }
  if (!currentPassword.value || !newPassword.value || !newPasswordConfirm.value) {
    showToast('Заповніть усі поля для зміни пароля', 'error')
    return { valid: false, shouldUpdate: false }
  }
  if (newPassword.value.length < 6) {
    showToast('Мінімальна довжина пароля 6 символів', 'error')
    return { valid: false, shouldUpdate: false }
  }
  if (newPassword.value !== newPasswordConfirm.value) {
    showToast('Паролі не співпадають', 'error')
    return { valid: false, shouldUpdate: false }
  }
  return { valid: true, shouldUpdate: true }
}

const save = async () => {
  if (!doctorId.value) {
    phone.value = ''
  }
  const { valid, shouldUpdate } = validatePasswords()
  if (!valid) return

  saving.value = true
  try {
    // Phone update (тільки якщо є лікар)
    if (doctorId.value) {
      await doctorApi.update(doctorId.value, { phone: phone.value })
    }

    // Avatar upload/remove (прив'язуємо до користувача, незалежно від ролі)
    if (avatarFile.value) {
      await userApi.uploadAvatar(avatarFile.value)
    } else if (removeAvatar.value) {
      await userApi.uploadAvatar(null, true)
    }

    // Password update (optional)
    if (shouldUpdate) {
      await userApi.updatePassword({
        current_password: currentPassword.value,
        password: newPassword.value,
        password_confirmation: newPasswordConfirm.value
      })
    }

    showToast('Профіль оновлено', 'success')
    emit('updated')
    open.value = false
  } catch (e: any) {
    const firstError = e?.response?.data?.errors && Object.values(e.response.data.errors)[0]?.[0]
    const msg =
      firstError ||
      e?.response?.data?.message ||
      e?.message ||
      'Не вдалося оновити профіль. Перевірте поточний пароль і спробуйте ще раз.'
    showToast(msg, 'error')
  } finally {
    saving.value = false
  }
}
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
          class="w-full max-w-2xl rounded-2xl bg-card text-text shadow-2xl border border-border p-6 space-y-6"
        >
          <div class="flex items-start gap-4">
            <UIAvatar
              :src="avatarPreview || avatarUrl || ''"
              :fallback-text="(user?.first_name || user?.name || 'U')[0]"
              :size="80"
            />
            <div class="space-y-2">
              <p class="text-lg font-semibold">Фото профілю</p>
              <div class="flex gap-2 flex-wrap">
                <input
                  ref="avatarInputRef"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="onAvatarChange"
                />
                <UIButton variant="secondary" size="sm" @click="avatarInputRef?.click()"
                  >Завантажити</UIButton
                >
                <UIButton variant="ghost" size="sm" @click="onRemoveAvatar">Видалити</UIButton>
              </div>
              <p class="text-xs text-text/60">Миттєвий превʼю зʼявиться після вибору файлу.</p>
            </div>
          </div>

          <div class="space-y-3">
            <p class="text-lg font-semibold">Контакти</p>
            <label class="space-y-1 text-sm text-text/80">
              <span class="text-xs text-text/60 uppercase">Email</span>
              <input
                :value="email"
                type="text"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm opacity-70"
                disabled
              />
            </label>
            <label v-if="doctorId" class="space-y-1 text-sm text-text/80">
              <span class="text-xs text-text/60 uppercase">Телефон</span>
              <input
                v-model="phone"
                type="text"
                class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm"
                placeholder="+380..."
              />
            </label>
            <p class="text-xs text-text/60">
              ПІБ, спеціалізація, клініка редагуються лише адміністратором.
            </p>
          </div>

          <div class="space-y-3">
            <p class="text-lg font-semibold">Зміна пароля</p>
            <div class="flex items-center gap-2 text-sm text-text/80">
              <input
                id="toggle-password"
                v-model="changePassword"
                type="checkbox"
                class="h-4 w-4 rounded border-border/80 bg-bg"
              />
              <label for="toggle-password" class="cursor-pointer select-none">Змінити пароль</label>
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
              <label class="space-y-1 text-sm text-text/80">
                <span class="text-xs text-text/60 uppercase">Поточний пароль</span>
                <div class="flex items-center gap-2">
                  <input
                    v-model="currentPassword"
                    :type="showCurrent ? 'text' : 'password'"
                    :disabled="!changePassword"
                    autocomplete="current-password"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                  />
                  <UIButton size="sm" variant="ghost" @click="showCurrent = !showCurrent">
                    {{ showCurrent ? 'Сховати' : 'Показати' }}
                  </UIButton>
                </div>
              </label>
              <label class="space-y-1 text-sm text-text/80">
                <span class="text-xs text-text/60 uppercase">Новий пароль</span>
                <div class="flex items-center gap-2">
                  <input
                    v-model="newPassword"
                    :type="showNew ? 'text' : 'password'"
                    :disabled="!changePassword"
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                  />
                  <UIButton size="sm" variant="ghost" @click="showNew = !showNew">
                    {{ showNew ? 'Сховати' : 'Показати' }}
                  </UIButton>
                </div>
              </label>
              <label class="space-y-1 text-sm text-text/80">
                <span class="text-xs text-text/60 uppercase">Підтвердження</span>
                <div class="flex items-center gap-2">
                  <input
                    v-model="newPasswordConfirm"
                    :type="showConfirm ? 'text' : 'password'"
                    :disabled="!changePassword"
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-bg border border-border/80 px-3 py-2 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
                  />
                  <UIButton size="sm" variant="ghost" @click="showConfirm = !showConfirm">
                    {{ showConfirm ? 'Сховати' : 'Показати' }}
                  </UIButton>
                </div>
              </label>
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <UIButton variant="ghost" size="sm" @click="open = false">Скасувати</UIButton>
            <UIButton variant="secondary" size="sm" :loading="saving" @click="save"
              >Зберегти</UIButton
            >
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>
