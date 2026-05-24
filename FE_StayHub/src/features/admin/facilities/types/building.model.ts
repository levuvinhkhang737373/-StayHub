import type { ElementType } from 'react'

export type BuildingStatus = 'active' | 'inactive' | 'maintenance'

export interface BuildingAmenity {
  label: string
  icon: ElementType
}

export interface BuildingImage {
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

export interface Building {
  id: number
  name: string
  slug?: string | null
  address?: string | null
  region_id?: number
  region_name?: string | null
  manager_admin_id?: number | null
  status: BuildingStatus
  status_value: number
  gender_policy?: number | null
  total_floors?: number | null
  description?: string | null
  primary_image?: BuildingImage | null
  images?: BuildingImage[]
  image_urls?: string[]
  images_count?: number
  manager_name?: string | null
  manager_phone?: string | null
  rooms_count?: number
  service_prices_count?: number
  notifications_count?: number
  expenses_count?: number
  asset_templates_count?: number
  created_at?: string | null
  updated_at?: string | null
}
