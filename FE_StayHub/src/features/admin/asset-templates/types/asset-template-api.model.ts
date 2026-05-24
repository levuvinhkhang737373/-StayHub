import type { AdminBuildingResource } from '../../facilities/types/facility-api.model'

export interface AdminAssetTemplateResource {
  id: number
  building_id?: number | null
  building_name?: string | null
  building?: AdminBuildingResource | null
  name: string
  slug?: string | null
  default_unit_name?: number | null
  default_unit_label?: string | null
  description?: string | null
  status: number
  status_label?: string | null
  created_by?: number | null
  creator_name?: string | null
  room_assets_count?: number
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminAssetTemplatePayload {
  building_id?: number
  name: string
  default_unit_name?: number
  description?: string
  status?: number
}
