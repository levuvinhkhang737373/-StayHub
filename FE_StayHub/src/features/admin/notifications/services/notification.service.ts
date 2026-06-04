import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { 
  AdminNotificationFilters, 
  AdminNotificationPayload, 
  AdminNotificationResource, 
  AdminNotificationPaginator 
} from '../types/notification-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminNotifications(params: AdminNotificationFilters = {}) {
  return apiRequest<AdminNotificationPaginator>({
    url: `admin/notifications${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminNotificationDetail(id: number) {
  return apiRequest<AdminNotificationResource>({
    url: `admin/notifications/${id}`,
    method: 'GET',
  })
}

export async function createAdminNotification(payload: AdminNotificationPayload) {
  return apiRequest<AdminNotificationResource>({
    url: 'admin/notifications',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminNotification(id: number, payload: AdminNotificationPayload) {
  return apiRequest<AdminNotificationResource>({
    url: `admin/notifications/${id}`,
    method: 'PUT',
    data: payload,
  })
}

export async function deleteAdminNotification(id: number) {
  return apiRequest<null>({
    url: `admin/notifications/${id}`,
    method: 'DELETE',
  })
}
