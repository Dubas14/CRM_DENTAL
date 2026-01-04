import apiClient from './apiClient'

export const userApi = {
  updatePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
    return apiClient.post('/user/password', payload)
  },
  uploadAvatar(file: File | null, remove = false) {
    const form = new FormData()
    if (file) {
      form.append('avatar', file)
    }
    if (remove) {
      form.append('remove', '1')
    }
    return apiClient.post('/user/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
}

export default userApi

