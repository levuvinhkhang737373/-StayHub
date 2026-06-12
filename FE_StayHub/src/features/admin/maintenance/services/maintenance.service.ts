import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { 
  AdminMaintenanceFilters, 
  AdminMaintenanceRequestResource, 
  AdminMaintenancePaginator 
} from '../types/maintenance-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminMaintenanceRequests(params: AdminMaintenanceFilters = {}) {
  return apiRequest<AdminMaintenancePaginator>({
    url: `admin/maintenance-requests${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminMaintenanceDetail(id: number) {
  return apiRequest<AdminMaintenanceRequestResource>({
    url: `admin/maintenance-requests/${id}`,
    method: 'GET',
  })
}

export async function assignMaintenanceStaff(id: number, assignedTo: number) {
  return apiRequest<AdminMaintenanceRequestResource>({
    url: `admin/maintenance-requests/${id}/assign`,
    method: 'PATCH',
    data: {
      assigned_to: assignedTo,
    },
  })
}

export async function updateMaintenanceStatus(
  id: number, 
  status: number, 
  note?: string, 
  afterImage?: File | null
) {
  // If there is a file, we must use FormData and method spoofing (POST with _method: PATCH)
  if (afterImage) {
    const formData = new FormData()
    formData.append('_method', 'PATCH')
    formData.append('status', String(status))
    if (note) formData.append('note', note)
    formData.append('after_image', afterImage)

    return apiRequest<AdminMaintenanceRequestResource>({
      url: `admin/maintenance-requests/${id}/status`,
      method: 'POST',
      data: formData,
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
  }

  // Otherwise, send a clean PATCH request with JSON
  return apiRequest<AdminMaintenanceRequestResource>({
    url: `admin/maintenance-requests/${id}/status`,
    method: 'PATCH',
    data: {
      status,
      note,
    },
  })
}
