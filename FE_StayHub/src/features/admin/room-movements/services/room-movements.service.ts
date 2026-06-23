import { apiRequest } from '../../../../shared/lib/api/api-client'
import type { AdminRoomMovementFilters, AdminRoomMovementPaginator, AdminRoomMovementResource } from '../types/room-movement-api.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })

  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchAdminRoomMovements(params: AdminRoomMovementFilters = {}) {
  return apiRequest<AdminRoomMovementPaginator<AdminRoomMovementResource>>({
    url: `admin/room-movements${buildQuery({ per_page: 20, ...params })}`,
    method: 'GET',
  })
}

export async function fetchAdminRoomMovementDetail(roomMovementId: number) {
  return apiRequest<AdminRoomMovementResource>({
    url: `admin/room-movements/${roomMovementId}`,
    method: 'GET',
  })
}
