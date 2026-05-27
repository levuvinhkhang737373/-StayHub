import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminServicePayload, AdminServiceResource } from '../types/service-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminServices(
  params: {
    keyword?: string
    service_type?: string
    charge_method?: number
    is_required?: boolean
    is_active?: boolean
    per_page?: number
  } = {},
) {
  return apiRequest<AdminServiceResource[]>({
    url: `admin/services${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminServiceDetail(serviceId: number) {
  return apiRequest<AdminServiceResource>({
    url: `admin/services/${serviceId}`,
    method: 'GET',
  })
}

export async function createAdminService(payload: AdminServicePayload) {
  return apiRequest<AdminServiceResource>({
    url: 'admin/services',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminService(serviceId: number, payload: AdminServicePayload) {
  return apiRequest<AdminServiceResource>({
    url: `admin/services/${serviceId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminServiceStatus(serviceId: number, status: boolean) {
  return apiRequest<AdminServiceResource>({
    url: `admin/services/${serviceId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminService(serviceId: number) {
  return apiRequest<null>({
    url: `admin/services/${serviceId}`,
    method: 'DELETE',
  })
}
