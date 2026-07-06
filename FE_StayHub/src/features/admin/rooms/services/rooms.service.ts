import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {  AdminRoomResource, AssetResource, BuildingResource, RoomTypeResource } from '../types/rooms.model'

const BUILDING_PAGE_SIZE = 100

interface PaginationMeta {
  current_page?: number
  last_page?: number
}

function normalizeResultList<T>(result: unknown): T[] {
  if (Array.isArray(result)) return result as T[]

  if (result && typeof result === 'object') {
    const paginated = result as { data?: unknown }
    if (Array.isArray(paginated.data)) return paginated.data as T[]
  }

  return []
}

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

function normalizePaginationMeta(result: unknown): PaginationMeta | null {
  if (!result || typeof result !== 'object' || Array.isArray(result)) return null

  const payload = result as { meta?: unknown; pagination?: unknown }
  const meta = payload.meta ?? payload.pagination

  if (!meta || typeof meta !== 'object' || Array.isArray(meta)) return null

  return meta as PaginationMeta
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
  return apiRequest<unknown>({
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
  const firstPage = await apiRequest<unknown>({
    url: `admin/buildings${buildQuery({ per_page: BUILDING_PAGE_SIZE, page: 1 })}`,
    method: 'GET',
  })

  const buildings = normalizeResultList<BuildingResource>(firstPage.result)
  const meta = normalizePaginationMeta(firstPage.result)
  const lastPage = Math.max(1, Number(meta?.last_page ?? 1))

  for (let page = 2; page <= lastPage; page += 1) {
    const nextPage = await apiRequest<unknown>({
      url: `admin/buildings${buildQuery({ per_page: BUILDING_PAGE_SIZE, page })}`,
      method: 'GET',
    })

    buildings.push(...normalizeResultList<BuildingResource>(nextPage.result))
  }

  return {
    ...firstPage,
    result: buildings,
  }
}
export async function fetchRoomType()
{
  const response = await apiRequest<unknown>({
    url: 'admin/room-types?per_page=1000',
    method: 'GET'
  });
  return {
    ...response,
    result: normalizeResultList<RoomTypeResource>(response.result)
  };
}

export async function fetchAssets()
{
  const response = await apiRequest<unknown>({
    url: 'admin/asset-templates?per_page=1000',
    method: 'GET'
  });
  return {
    ...response,
    result: normalizeResultList<AssetResource>(response.result)
  };
}

export async function createAssetTemplate(payload: { name: string; default_unit_name?: number; description?: string }) {
  return apiRequest<unknown>({
    url: 'admin/asset-templates',
    method: 'POST',
    data: payload
  });
}

export async function createRoomType(payload: { name: string; description?: string }) {
  return apiRequest<unknown>({
    url: 'admin/room-types',
    method: 'POST',
    data: payload
  });
}


