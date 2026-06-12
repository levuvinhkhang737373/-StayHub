import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {  AdminRoomResource, AssetResource, BuildingResource, RoomFormDataPayload, RoomTypeResource } from '../types/rooms.model'

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

export async function createAdminRoom(payload: FormData) {
  return apiRequest<any>({
    url: 'admin/room',
    method: 'POST',
    data: payload,
    headers: {
      'Content-Type': 'multipart/form-data', // Đảm bảo header nhận file
    },
  })
}

export async function updateAdminRoom(roomTypeId: number, payload: FormData) {
  return apiRequest<AdminRoomResource>({
    url: `admin/room/${roomTypeId}`,
    // CHÚ Ý: Đổi từ 'PUT' thành 'POST' khi gửi FormData chứa File (Laravel yêu cầu)
    method: 'POST', 
    data: payload,
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });
}
export async function updateAdminRoomStatus(roomTypeId: number) {
  return apiRequest<AdminRoomResource>({
    url: `admin/room/${roomTypeId}/status`,
    method: 'PATCH',
    
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
export async function fetchRoomType()
{
  return apiRequest<RoomTypeResource[]>({
    url:'admin/room-types',
    method:'GET'
  });
}

export async function fetchAssets()
{
  return apiRequest<AssetResource[]>({
    url:'admin/asset-templates',
    method:'GET'
  });
}


