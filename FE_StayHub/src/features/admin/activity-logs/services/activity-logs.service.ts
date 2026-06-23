import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminActivityLogFilters, AdminActivityLogPaginator, AdminActivityLogResource } from '../types/activity-log-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminActivityLogs(params: AdminActivityLogFilters = {}) {
  return apiRequest<AdminActivityLogPaginator<AdminActivityLogResource>>({
    url: `admin/activity-logs${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminActivityLogDetail(activityLogId: number) {
  return apiRequest<AdminActivityLogResource>({
    url: `admin/activity-logs/${activityLogId}`,
    method: 'GET',
  })
}
