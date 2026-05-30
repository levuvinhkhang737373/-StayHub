import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminRoomTypePayload, AdminRoomTypeResource } from '../types/room-type-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminRoomTypes(
  params: {
    keyword?: string
    status?: number
    building_id?: number
    only_global?: boolean
    created_by_me?: boolean
    per_page?: number
  } = {},
) {
  return apiRequest<AdminRoomTypeResource[]>({
    url: `admin/room-types${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminRoomTypeDetail(roomTypeId: number) {
  return apiRequest<AdminRoomTypeResource>({
    url: `admin/room-types/${roomTypeId}`,
    method: 'GET',
  })
}

export async function createAdminRoomType(payload: AdminRoomTypePayload) {
  return apiRequest<AdminRoomTypeResource>({
    url: 'admin/room-types',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminRoomType(roomTypeId: number, payload: AdminRoomTypePayload) {
  return apiRequest<AdminRoomTypeResource>({
    url: `admin/room-types/${roomTypeId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminRoomTypeStatus(roomTypeId: number, status: number) {
  return apiRequest<AdminRoomTypeResource>({
    url: `admin/room-types/${roomTypeId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminRoomType(roomTypeId: number) {
  return apiRequest<null>({
    url: `admin/room-types/${roomTypeId}`,
    method: 'DELETE',
  })
}
