export interface AdminPaginationMeta {
  current_page?: number
  from?: number | null
  last_page?: number
  path?: string
  per_page?: number
  to?: number | null
  total?: number
}

export interface AdminPaginator<T> {
  data: T[]
  links?: unknown
  meta?: AdminPaginationMeta | null
}

export interface AdminRoomTypeRoomResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  room_number: string
  slug?: string | null
  base_price?: string | null
  max_occupants: number
  current_occupants: number
  status: number
}

import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'

export interface AdminRoomTypeResource {
  id: number
  name: string
  slug?: string | null
  building_id?: number | null
  building_name?: string | null
  building?: AdminBuildingResource | null
  description?: string | null
  status: number
  status_label?: string | null
  is_active?: boolean
  created_by?: number | null
  creator_name?: string | null
  creator?: {
    id: number
    full_name?: string | null
  } | null
  rooms_count?: number
  rooms?: AdminRoomTypeRoomResource[]
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminRoomTypePayload {
  name: string
  building_id?: number
  description?: string
  status?: number
}
