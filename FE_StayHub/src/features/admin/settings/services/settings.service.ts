import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminSettingPayload, AdminSettingResource } from '../types/setting-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminSettings(
  params: {
    keyword?: string
    building_id?: number
    only_global?: boolean
    is_public?: boolean
    per_page?: number
  } = {},
) {
  return apiRequest<AdminSettingResource[]>({
    url: `admin/settings${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminSettingDetail(settingId: number) {
  return apiRequest<AdminSettingResource>({
    url: `admin/settings/${settingId}`,
    method: 'GET',
  })
}

export async function createAdminSetting(payload: AdminSettingPayload) {
  return apiRequest<AdminSettingResource>({
    url: 'admin/settings',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminSetting(settingId: number, payload: AdminSettingPayload) {
  return apiRequest<AdminSettingResource>({
    url: `admin/settings/${settingId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminSettingPublic(settingId: number) {
  return apiRequest<AdminSettingResource>({
    url: `admin/settings/${settingId}/toggle-public`,
    method: 'PATCH',
  })
}

export async function deleteAdminSetting(settingId: number) {
  return apiRequest<null>({
    url: `admin/settings/${settingId}`,
    method: 'DELETE',
  })
}
