import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminPaginator, AdminTenantFilters, AdminTenantPayload, AdminTenantResource, AdminTenantStatusPayload } from '../types/tenant-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

function hasFiles(payload: AdminTenantPayload) {
  return Boolean(payload.front_image || payload.back_image)
}

function toFormData(payload: AdminTenantPayload, method?: 'PUT') {
  const formData = new FormData()

  if (method) {
    formData.append('_method', method)
  }

  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return

    if (value instanceof File) {
      formData.append(key, value)
      return
    }

    if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0')
      return
    }

    formData.append(key, String(value))
  })

  return formData
}

export async function fetchAdminTenants(params: AdminTenantFilters = {}) {
  return apiRequest<AdminPaginator<AdminTenantResource>>({
    url: `admin/tenants${buildQuery({ per_page: 10, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminTenantDetail(tenantId: number) {
  return apiRequest<AdminTenantResource>({
    url: `admin/tenants/${tenantId}`,
    method: 'GET',
  })
}

export async function createAdminTenant(payload: AdminTenantPayload) {
  return apiRequest<AdminTenantResource>({
    url: 'admin/tenants',
    method: 'POST',
    data: toFormData(payload),
  })
}

export async function updateAdminTenant(tenantId: number, payload: AdminTenantPayload) {
  if (hasFiles(payload)) {
    return apiRequest<AdminTenantResource>({
      url: `admin/tenants/${tenantId}`,
      method: 'POST',
      data: toFormData(payload, 'PUT'),
    })
  }

  return apiRequest<AdminTenantResource>({
    url: `admin/tenants/${tenantId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminTenantStatus(tenantId: number, payload: AdminTenantStatusPayload) {
  return apiRequest<AdminTenantResource>({
    url: `admin/tenants/${tenantId}/status`,
    method: 'PATCH',
    data: payload,
  })
}

export async function deleteAdminTenant(tenantId: number) {
  return apiRequest<null>({
    url: `admin/tenants/${tenantId}`,
    method: 'DELETE',
  })
}
