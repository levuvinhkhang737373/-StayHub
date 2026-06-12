import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminPaginator, AdminVehiclePayload, AdminVehicleResource } from '../types/vehicle.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminVehicles(
  params: {
    keyword?: string
    tenant_id?: number
    vehicle_type?: number
    is_active?: boolean
    per_page?: number
    page?: number
  } = {},
) {
  return apiRequest<AdminPaginator<AdminVehicleResource>>({
    url: `admin/vehicles${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminVehicleDetail(vehicleId: number) {
  return apiRequest<AdminVehicleResource>({
    url: `admin/vehicles/${vehicleId}`,
    method: 'GET',
  })
}

export async function createAdminVehicle(payload: AdminVehiclePayload) {
  return apiRequest<AdminVehicleResource>({
    url: 'admin/vehicles',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminVehicle(vehicleId: number, payload: AdminVehiclePayload) {
  return apiRequest<AdminVehicleResource>({
    url: `admin/vehicles/${vehicleId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminVehicleStatus(vehicleId: number, status: boolean) {
  return apiRequest<AdminVehicleResource>({
    url: `admin/vehicles/${vehicleId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminVehicle(vehicleId: number) {
  return apiRequest<null>({
    url: `admin/vehicles/${vehicleId}`,
    method: 'DELETE',
  })
}
