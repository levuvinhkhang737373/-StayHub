import type { BuildingStatus } from './building.model'
import type { AdminServiceResource } from '../../services/types/service-api.model'

export interface LaravelPaginator<T> {
  data: T[]
  links?: unknown
  meta?: {
    current_page?: number
    from?: number | null
    last_page?: number
    path?: string
    per_page?: number
    to?: number | null
    total?: number
  }
}

export interface AdminRegionResource {
  id: number
  name: string
  code: string
  parent_id: number | null
  parent_name?: string | null
  level: string
  slug: string
  path: string | null
  description?: string | null
  is_active: boolean
  status: boolean
  sort_order: number
  children_count?: number
  buildings_count?: number
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminRegionPayload {
  parent_id?: number
  code: string
  name: string
  description?: string
  is_active?: boolean
}

export interface AdminManagerResource {
  id: number
  username: string
  full_name: string
  email?: string | null
  phone?: string | null
  avatar?: string | null
  role: 'quan_tri_tong' | 'quan_ly_toa_nha' | 'ky_thuat' | number
  status: 'hoat_dong' | 'ngung_hoat_dong' | 'bi_khoa' | number
  managed_buildings_count?: number
}

export interface AdminBuildingImageResource {
  id: number
  building_id: number
  image_path: string
  image_url: string
  is_primary: boolean
  sort_order: number
  status: number
  uploaded_by?: number | null
  uploader_name?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminBuildingImageMetadata {
  is_primary?: boolean
  sort_order?: number
  status?: number
}



export interface AdminBuildingServicePriceResource {
  id: number
  service_id: number
  service?: AdminServiceResource | null
  service_name?: string | null
  building_id?: number | null
  building_name?: string | null
  price?: string | null
  effective_from?: string | null
  effective_to?: string | null
  status?: number | null
  status_label?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminBuildingSettingResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  setting_label: string
  setting_value?: string | null
  description?: string | null
  is_public: boolean
  is_public_label?: string | null
  created_by?: number | null
  creator_name?: string | null
  created_at?: string | null
  updated_at?: string | null
}



export interface AdminBuildingServicePricePayload {
  id?: number
  service_id: number
  price: string
  effective_from?: string
  effective_to?: string
  status?: number
}

export interface AdminBuildingSettingPayload {
  id?: number
  setting_label: string
  setting_value?: string
  description?: string
  is_public?: boolean
}

export interface AdminBuildingPayload {
  region_id?: number
  manager_admin_id?: number
  name?: string
  address?: string
  description?: string
  total_floors?: number
  gender_policy?: number
  status?: number
  images?: File[]
  image_metadata?: AdminBuildingImageMetadata[]
  delete_image_ids?: number[]
  primary_image_id?: number

  service_prices?: AdminBuildingServicePricePayload[]
  delete_service_price_ids?: number[]
  setting_ids?: number[]
  settings?: AdminBuildingSettingPayload[]
  delete_setting_ids?: number[]
}

export interface AdminBuildingResource {
  id: number
  region_id: number
  region_name?: string | null
  region?: AdminRegionResource | null
  manager_admin_id?: number | null
  manager_name?: string | null
  manager?: {
    id: number
    username?: string | null
    full_name?: string | null
    email?: string | null
    phone?: string | null
    role?: string | number | null
    status?: string | number | null
  } | null
  name: string
  slug?: string | null
  address?: string | null
  total_floors?: number | null
  gender_policy?: number | null
  description?: string | null
  status: number
  created_by?: number | null
  creator_name?: string | null
  primary_image?: AdminBuildingImageResource | null
  images?: AdminBuildingImageResource[]

  service_prices?: AdminBuildingServicePriceResource[]
  settings?: AdminBuildingSettingResource[]
  rooms?: Array<{
    id: number
    building_id: number
    room_number: string
    status: number
  }>
  images_count?: number
  rooms_count?: number

  service_prices_count?: number
  settings_count?: number
  notifications_count?: number
  expenses_count?: number

  created_at?: string | null
  updated_at?: string | null
}

export interface AdminBuildingDetailResource extends AdminBuildingResource {
  deleted_at?: string | null
}

export function mapApiStatusToUiStatus(status: AdminBuildingResource['status']): BuildingStatus {
  if (Number(status) === 2) return 'inactive'
  if (Number(status) === 3) return 'maintenance'
  return 'active'
}

export function mapUiStatusToApiStatus(status: BuildingStatus | 'all') {
  if (status === 'active') return 1
  if (status === 'inactive') return 2
  if (status === 'maintenance') return 3
  return undefined
}
