import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminRoomPayload, AdminRoomResource, BuildingResource } from '../types/rooms.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminRooms(
  params: {
    keyword?: string
    status?: number
    building_id?: number
    only_global?: boolean
    created_by_me?: boolean
    per_page?: number
  } = {},
) {
  return apiRequest<AdminRoomResource[]>({
    url: `admin/room${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminRoomDetail(roomTypeId: number) {
  return apiRequest<AdminRoomResource>({
    url: `admin/room/${roomTypeId}`,
    method: 'GET',
  })
}

export async function createAdminRoom(payload: AdminRoomPayload) {
  return apiRequest<AdminRoomResource>({
    url: 'admin/room',
    method: 'POST',
    data: payload,
  })
}

export async function updateAdminRoom(roomTypeId: number, payload: AdminRoomPayload) {
  return apiRequest<AdminRoomResource>({
    url: `admin/room/${roomTypeId}`,
    method: 'PUT',
    data: payload,
  })
}

export async function updateAdminRoomStatus(roomTypeId: number, status: number) {
  return apiRequest<AdminRoomResource>({
    url: `admin/room/${roomTypeId}/status`,
    method: 'PATCH',
    data: { status },
  })
}

export async function deleteAdminRoom(roomTypeId: number) {
  return apiRequest<null>({
    url: `admin/room/${roomTypeId}`,
    method: 'DELETE',
  })
}

export async function fetchBuilding()
{
  return apiRequest<BuildingResource[]>({
    url:'admin/buildings',
    method:'GET'
  });
}
