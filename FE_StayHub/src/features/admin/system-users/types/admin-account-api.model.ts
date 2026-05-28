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
  meta?: AdminPaginationMeta
}

export interface AdminManagedBuildingResource {
  id: number
  name: string
  slug?: string | null
  address?: string | null
  status?: number | string | boolean | null
}

export interface AdminAccountResource {
  id: number
  username: string
  full_name: string
  email: string | null
  phone: string | null
  avatar_url?: string | null
  role: number
  role_label?: string | null
  status: number
  status_label?: string | null
  gender?: number | null
  gender_label?: string | null
  address?: string | null
  has_faceid: boolean
  image_path_faceid?: string | null
  managed_buildings?: AdminManagedBuildingResource[]
  managed_building_names?: string[]
  created_faceid_at?: string | null
  updated_faceid_at?: string | null
  managed_buildings_count?: number
  created_regions_count?: number
  created_buildings_count?: number
  created_room_types_count?: number
  created_rooms_count?: number
  created_asset_templates_count?: number
  created_services_count?: number
  settings_count?: number
  logs_count?: number
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminAccountFilters {
  keyword?: string
  role?: number
  status?: number
  page?: number
  per_page?: number
}

export interface AdminAccountPayload {
  username?: string
  full_name: string
  email: string
  phone?: string | null
  password?: string
  role: number
  status?: number
  gender?: number | null
  address?: string | null
  avatar_url?: string | null
}

export interface AdminAccountStatusPayload {
  status: number
  reason?: string
}
