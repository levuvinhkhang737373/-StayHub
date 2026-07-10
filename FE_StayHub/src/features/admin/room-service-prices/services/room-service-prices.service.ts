import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  RoomServicePriceFilters,
  RoomServicePriceListResult,
  RoomServicePriceRoomResource,
  UpdateRoomServicePricesPayload,
} from '../types/room-service-price.model'

function buildQuery(params: Record<string, string | number | boolean | null | undefined>) {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  })
  const queryString = query.toString()
  return queryString ? `?${queryString}` : ''
}

export async function fetchRoomServicePrices(filters: RoomServicePriceFilters) {
  return apiRequest<RoomServicePriceListResult>({
    url: `admin/room-service-prices${buildQuery({ ...filters })}`,
    method: 'GET',
  })
}

export async function fetchRoomServicePriceDetail(roomId: number, filters: Pick<RoomServicePriceFilters, 'billing_month' | 'billing_year'>) {
  return apiRequest<RoomServicePriceRoomResource>({
    url: `admin/rooms/${roomId}/service-prices${buildQuery(filters)}`,
    method: 'GET',
  })
}

export async function updateRoomServicePrices(roomId: number, payload: UpdateRoomServicePricesPayload) {
  return apiRequest<RoomServicePriceRoomResource>({
    url: `admin/rooms/${roomId}/service-prices`,
    method: 'PUT',
    data: payload,
  })
}
