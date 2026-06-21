import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {  AdminRoomResource, AssetResource, BuildingResource, RoomTypeResource } from '../types/rooms.model'

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
    room_type_id?: number
    only_global?: boolean
    created_by_me?: boolean
    per_page?: number
  } = {},
) {
  return apiRequest<AdminRoomResource[]>({
    url: `admin/rooms${buildQuery({ per_page: 100, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminRoomDetail(roomTypeId: number) {
  return apiRequest<AdminRoomResource>({
    url: `admin/rooms/${roomTypeId}`,
    method: 'GET',
  })
}

export async function createAdminRoom(payload: FormData) {
  return apiRequest<any>({
    url: 'admin/rooms',
    method: 'POST',
    data: payload,
    headers: {
      'Content-Type': 'multipart/form-data', // Đảm bảo header nhận file
    },
  })
}

export async function updateAdminRoom(roomTypeId: number, payload: FormData) {
  return apiRequest<AdminRoomResource>({
    url: `admin/rooms/${roomTypeId}`,
    // CHÚ Ý: Đổi từ 'PUT' thành 'POST' khi gửi FormData chứa File (Laravel yêu cầu)
    method: 'POST', 
    data: payload,
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });
}
export async function updateAdminRoomStatus(roomTypeId: number, status?: number) {
  return apiRequest<AdminRoomResource>({
    url: `admin/rooms/${roomTypeId}/status`,
    method: 'PATCH',
    data: status !== undefined ? { status } : undefined
  })
}

export async function deleteAdminRoom(roomTypeId: number) {
  return apiRequest<null>({
    url: `admin/rooms/${roomTypeId}`,
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
export async function fetchRoomType()
{
  const response = await apiRequest<any>({
    url: 'admin/room-types?per_page=1000',
    method: 'GET'
  });
  return {
    ...response,
    result: (response.result?.data ?? response.result ?? []) as RoomTypeResource[]
  };
}

export async function fetchAssets()
{
  const response = await apiRequest<any>({
    url: 'admin/asset-templates?per_page=1000',
    method: 'GET'
  });
  return {
    ...response,
    result: (response.result?.data ?? response.result ?? []) as AssetResource[]
  };
}

