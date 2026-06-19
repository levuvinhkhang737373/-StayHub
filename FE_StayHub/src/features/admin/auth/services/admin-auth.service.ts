import { apiRequest, getCsrfCookie } from '../../../../shared/lib/api/api-client'
import type {
  AdminChangePasswordPayload,
  AdminLoginPayload,
  AdminLoginResult,
  AdminProfile,
} from '../types/admin-auth.model'

export async function loginAdmin(payload: AdminLoginPayload) {
  await getCsrfCookie()

  return apiRequest<AdminLoginResult>({
    url: 'admin/login',
    method: 'POST',
    data: payload,
  })
}

export async function fetchAdminMe() {
  return apiRequest<AdminProfile>({
    url: 'admin/me',
    method: 'GET',
  })
}

export async function loginAdminWithFace(images: Blob[]) {
  await getCsrfCookie()

  const formData = new FormData()
  images.forEach((image, index) => formData.append('images[]', image, `admin-face-login-${index}.jpg`))

  return apiRequest<AdminLoginResult>({
    url: 'admin/face-login',
    method: 'POST',
    data: formData,
  })
}

export async function registerAdminFaceId(images: Blob[]) {
  const formData = new FormData()
  images.forEach((image, index) => formData.append('images[]', image, `admin-face-id-${index}.jpg`))

  return apiRequest<AdminLoginResult>({
    url: 'admin/face-id/register',
    method: 'POST',
    data: formData,
  })
}

export async function deleteAdminFaceId(currentPassword: string) {
  return apiRequest<AdminLoginResult>({
    url: 'admin/face-id',
    method: 'DELETE',
    data: { current_password: currentPassword },
  })
}

export async function changeAdminPassword(payload: AdminChangePasswordPayload) {
  return apiRequest<AdminLoginResult>({
    url: 'admin/password',
    method: 'PATCH',
    data: payload,
  })
}

export async function updateAdminProfile(payload: { full_name: string; phone?: string }) {
  return apiRequest<AdminLoginResult>({
    url: 'admin/profile',
    method: 'PATCH',
    data: payload,
  })
}

export async function logoutAdmin() {
  return apiRequest<null>({
    url: 'admin/logout',
    method: 'POST',
  })
}
