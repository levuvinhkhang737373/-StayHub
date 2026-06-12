import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminMeterDevicePayload, AdminMeterDeviceResource, AdminPaginator } from '../types/meter-api.model'
import type { AdminServiceResource } from '../../services/types/service-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminMeterDevices(
  params: {
    keyword?: string
    room_id?: number
    service_id?: number
    meter_type?: number
    status?: number
    per_page?: number
    page?: number
  } = {},
) {
  return apiRequest<AdminPaginator<AdminMeterDeviceResource>>({
    url: `admin/meter-devices${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminMeterDeviceDetail(meterDeviceId: number) {
  return apiRequest<AdminMeterDeviceResource>({
    url: `admin/meter-devices/${meterDeviceId}`,
    method: 'GET',
  })
}

export async function createAdminMeterDevice(payload: AdminMeterDevicePayload) {
  return apiRequest<AdminMeterDeviceResource>({
    url: 'admin/meter-devices',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminMeterDevice(meterDeviceId: number, payload: AdminMeterDevicePayload) {
  return apiRequest<AdminMeterDeviceResource>({
    url: `admin/meter-devices/${meterDeviceId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminMeterDeviceStatus(meterDeviceId: number, status: number, payload: { replaced_by_meter_id?: number | null } = {}) {
  return apiRequest<AdminMeterDeviceResource>({
    url: `admin/meter-devices/${meterDeviceId}/status`,
    method: 'PATCH',
    data: { status, ...payload },
  })
}

export async function deleteAdminMeterDevice(meterDeviceId: number) {
  return apiRequest<null>({
    url: `admin/meter-devices/${meterDeviceId}`,
    method: 'DELETE',
  })
}

export async function fetchAdminServices(params: { keyword?: string; status?: number; per_page?: number } = {}) {
  return apiRequest<AdminServiceResource[]>({
    url: `admin/services${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}
